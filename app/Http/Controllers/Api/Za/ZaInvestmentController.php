<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Za;

use App\Http\Controllers\Controller;
use App\Http\Requests\Za\Investment\CalculateCgtRequest;
use App\Http\Requests\Za\Investment\RecordHoldingDisposalRequest;
use App\Http\Requests\Za\Investment\StoreHoldingPurchaseRequest;
use App\Http\Requests\Za\Investment\StoreZaInvestmentAccountRequest;
use App\Http\Resources\Za\ZaHoldingLotResource;
use App\Http\Resources\Za\ZaHoldingResource;
use App\Http\Resources\Za\ZaInvestmentAccountResource;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use Fynla\Core\Contracts\InvestmentEngine;
use Fynla\Core\Money\Currency;
use Fynla\Core\Money\Money;
use Fynla\Packs\Za\Investment\ZaBaseCostTracker;
use Fynla\Packs\Za\Investment\ZaCgtCalculator;
use Fynla\Packs\Za\Models\ZaHoldingLot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * HTTP adapter over the ZA pack's investment domain (WS 1.3c).
 *
 * Thin proxy: every method resolves a pack.za.investment* container binding
 * and delegates. No business logic here. Pack owns the calculations; app
 * owns HTTP + auth + validation.
 *
 * Internal arithmetic in dashboard() uses Money VO per ADR-005; wire
 * format stays integer minor units.
 */
class ZaInvestmentController extends Controller
{
    public function __construct(
        private readonly ZaBaseCostTracker $lots,
        private readonly ZaCgtCalculator $cgt,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $taxYear = (string) $request->query('tax_year', $this->currentZaTaxYear());

        /** @var InvestmentEngine $engine */
        $engine = app('pack.za.investment');

        $userHoldingIds = Holding::query()
            ->whereHasMorph('holdable', [InvestmentAccount::class], function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)->where('country_code', 'ZA');
            })
            ->pluck('id')
            ->all();

        $zar = Currency::ZAR();
        $totalOpenCost = new Money(0, $zar);
        $lotCount = 0;
        if (! empty($userHoldingIds)) {
            $lotCount = (int) ZaHoldingLot::query()
                ->whereIn('holding_id', $userHoldingIds)
                ->where('quantity_open', '>', 0)
                ->count();
            foreach ($userHoldingIds as $hid) {
                $totalOpenCost = $totalOpenCost->plus(
                    new Money($this->lots->openCostBasisMinor((int) $hid), $zar),
                );
            }
        }

        return response()->json([
            'data' => [
                'tax_year' => $taxYear,
                'wrappers' => $engine->getTaxWrappers(),
                'allowances' => $engine->getAnnualAllowances($taxYear),
                'open_lot_summary' => [
                    'total_open_cost_basis_minor' => $totalOpenCost->minor,
                    'lot_count' => $lotCount,
                ],
            ],
        ]);
    }

    public function listAccounts(Request $request): JsonResponse
    {
        $accounts = InvestmentAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('country_code', 'ZA')
            ->orderByDesc('current_value')
            ->get();

        return response()->json(['data' => ZaInvestmentAccountResource::collection($accounts)]);
    }

    public function storeAccount(StoreZaInvestmentAccountRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['country_code'] = 'ZA';
        $data['country'] = 'South Africa';
        $data['ownership_type'] = 'individual';

        $account = InvestmentAccount::create($data);

        return response()->json(['data' => new ZaInvestmentAccountResource($account)], 201);
    }

    public function listHoldings(Request $request): JsonResponse
    {
        $accountId = $request->query('account_id');

        $query = Holding::query()
            ->whereHasMorph('holdable', [InvestmentAccount::class], function ($q) use ($request, $accountId) {
                $q->where('user_id', $request->user()->id)->where('country_code', 'ZA');
                if ($accountId) {
                    $q->where('id', $accountId);
                }
            });

        $holdings = $query->get()->map(function (Holding $h) {
            $h->additional = [
                'open_quantity' => $this->lots->openQuantity((int) $h->id),
                'open_lot_count' => count($this->lots->openLots((int) $h->id)),
            ];

            return $h;
        });

        return response()->json(['data' => ZaHoldingResource::collection($holdings)]);
    }

    public function listLots(Request $request, int $holdingId): JsonResponse
    {
        $owns = Holding::query()
            ->where('id', $holdingId)
            ->whereHasMorph('holdable', [InvestmentAccount::class], function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)->where('country_code', 'ZA');
            })
            ->exists();

        if (! $owns) {
            return response()->json(['message' => 'Holding not found'], 404);
        }

        $lots = ZaHoldingLot::query()
            ->where('holding_id', $holdingId)
            ->where('quantity_open', '>', 0)
            ->orderBy('acquisition_date')
            ->get();

        return response()->json(['data' => ZaHoldingLotResource::collection($lots)]);
    }

    public function storePurchase(StoreHoldingPurchaseRequest $request): JsonResponse
    {
        $data = $request->validated();

        $owns = Holding::query()
            ->where('id', $data['holding_id'])
            ->whereHasMorph('holdable', [InvestmentAccount::class], function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)->where('country_code', 'ZA');
            })
            ->exists();

        if (! $owns) {
            return response()->json(['message' => 'Holding not found'], 404);
        }

        try {
            $lotId = $this->lots->recordPurchase(
                userId: $request->user()->id,
                holdingId: (int) $data['holding_id'],
                quantity: (float) $data['quantity'],
                costMinor: (int) $data['cost_minor'],
                acquisitionDate: $data['acquisition_date'],
                notes: $data['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'lot_id' => $lotId,
                'open_cost_basis_minor' => $this->lots->openCostBasisMinor((int) $data['holding_id']),
                'open_quantity' => $this->lots->openQuantity((int) $data['holding_id']),
            ],
        ], 201);
    }

    public function recordDisposal(RecordHoldingDisposalRequest $request): JsonResponse
    {
        $data = $request->validated();

        $owns = Holding::query()
            ->where('id', $data['holding_id'])
            ->whereHasMorph('holdable', [InvestmentAccount::class], function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)->where('country_code', 'ZA');
            })
            ->exists();

        if (! $owns) {
            return response()->json(['message' => 'Holding not found'], 404);
        }

        try {
            $result = $this->lots->recordDisposal(
                userId: $request->user()->id,
                holdingId: (int) $data['holding_id'],
                quantity: (float) $data['quantity'],
                disposalDate: $data['disposal_date'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function calculateCgt(CalculateCgtRequest $request): JsonResponse
    {
        $data = $request->validated();
        $wrapper = $data['wrapper_code'];
        $gain = (int) $data['gain_minor'];
        $taxYear = $data['tax_year'];

        $result = match ($wrapper) {
            'tfsa' => [
                'tax_due_minor' => 0,
                'exclusion_applied_minor' => 0,
                'included_minor' => 0,
                'marginal_rate' => 0.0,
                'note' => 'Tax-Free Savings Account: no Capital Gains Tax.',
            ],
            'endowment' => $this->cgt->calculateEndowmentCgt($gain, $taxYear),
            'discretionary' => $this->cgt->calculateDiscretionaryCgt(
                gainMinor: $gain,
                otherTaxableIncomeMinor: (int) $data['income_minor'],
                age: (int) $data['age'],
                taxYear: $taxYear,
            ),
        };

        return response()->json(['data' => $result]);
    }

    /**
     * SA tax year runs 1 March to 28/29 February. Label format '2026/27'.
     */
    private function currentZaTaxYear(): string
    {
        $now = now();
        $startYear = $now->month >= 3 ? $now->year : $now->year - 1;

        return sprintf('%d/%02d', $startYear, ($startYear + 1) % 100);
    }
}
