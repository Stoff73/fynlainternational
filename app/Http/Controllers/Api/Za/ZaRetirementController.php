<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Za;

use App\Http\Controllers\Controller;
use App\Http\Requests\Za\Retirement\CalculateTaxReliefRequest;
use App\Http\Requests\Za\Retirement\CompulsoryApportionRequest;
use App\Http\Requests\Za\Retirement\LifeAnnuityQuoteRequest;
use App\Http\Requests\Za\Retirement\LivingAnnuityQuoteRequest;
use App\Http\Requests\Za\Retirement\Reg28CheckRequest;
use App\Http\Requests\Za\Retirement\SimulateSavingsPotWithdrawalRequest;
use App\Http\Requests\Za\Retirement\StoreContributionRequest;
use App\Http\Requests\Za\Retirement\StoreFundRequest;
use App\Http\Resources\Za\Retirement\Reg28SnapshotResource;
use App\Http\Resources\Za\Retirement\ZaAnnuityQuoteResource;
use App\Http\Resources\Za\Retirement\ZaRetirementBucketResource;
use App\Http\Resources\Za\Retirement\ZaRetirementFundResource;
use App\Models\DCPension;
use Fynla\Core\Money\Currency;
use Fynla\Core\Money\Money;
use Fynla\Packs\Za\Models\ZaReg28Snapshot;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Fynla\Packs\Za\Retirement\ZaCompulsoryAnnuitisationService;
use Fynla\Packs\Za\Retirement\ZaContributionSplitService;
use Fynla\Packs\Za\Retirement\ZaLifeAnnuityCalculator;
use Fynla\Packs\Za\Retirement\ZaLivingAnnuityCalculator;
use Fynla\Packs\Za\Retirement\ZaReg28Monitor;
use Fynla\Packs\Za\Retirement\ZaRetirementEngine;
use Fynla\Packs\Za\Retirement\ZaRetirementFundBucketRepository;
use Fynla\Packs\Za\Retirement\ZaSavingsPotWithdrawalSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * HTTP adapter over the ZA retirement domain (WS 1.4d).
 *
 * Thin proxy: every method resolves a pack binding and delegates.
 * No business logic here. Pack owns calculations; app owns HTTP.
 *
 * Internal arithmetic in dashboard() uses Money VO (ADR-005).
 */
class ZaRetirementController extends Controller
{
    public function __construct(
        private readonly ZaContributionSplitService $splitter,
        private readonly ZaRetirementFundBucketRepository $buckets,
        private readonly ZaSavingsPotWithdrawalSimulator $simulator,
        private readonly ZaLivingAnnuityCalculator $livingAnnuity,
        private readonly ZaLifeAnnuityCalculator $lifeAnnuity,
        private readonly ZaCompulsoryAnnuitisationService $compulsory,
        private readonly ZaReg28Monitor $reg28,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $taxYear = (string) $request->query('tax_year', $this->currentZaTaxYear());

        /** @var ZaRetirementEngine $engine */
        $engine = app('pack.za.retirement');

        $fundIds = DCPension::query()
            ->where('user_id', $userId)
            ->where('country_code', 'ZA')
            ->pluck('id')
            ->all();

        $zar = Currency::ZAR();
        $total = new Money(0, $zar);
        if (! empty($fundIds)) {
            $rows = ZaRetirementFundBucket::query()
                ->where('user_id', $userId)
                ->whereIn('fund_holding_id', $fundIds)
                ->get();
            foreach ($rows as $b) {
                $total = $total->plus(new Money(
                    (int) $b->vested_balance_minor
                    + (int) $b->provident_vested_pre2021_balance_minor
                    + (int) $b->savings_balance_minor
                    + (int) $b->retirement_balance_minor,
                    $zar,
                ));
            }
        }

        return response()->json([
            'data' => [
                'tax_year' => $taxYear,
                'annual_allowance_minor' => $engine->getAnnualAllowance($taxYear),
                'total_balance_minor' => $total->minor,
                'fund_count' => count($fundIds),
            ],
        ]);
    }

    public function listFunds(Request $request): JsonResponse
    {
        $funds = DCPension::query()
            ->where('user_id', $request->user()->id)
            ->where('country_code', 'ZA')
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => ZaRetirementFundResource::collection($funds)]);
    }

    public function storeFund(StoreFundRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        $fund = DCPension::create([
            'user_id' => $userId,
            'pension_type' => $data['fund_type'],
            'scheme_type' => 'personal',
            'provider' => $data['provider'],
            'scheme_name' => $data['scheme_name'] ?? null,
            'member_number' => $data['member_number'] ?? null,
            'country_code' => 'ZA',
        ]);

        $bucket = $this->buckets->findOrCreate($userId, (int) $fund->id);

        if (($data['starting_vested_minor'] ?? 0) > 0 ||
            ($data['starting_savings_minor'] ?? 0) > 0 ||
            ($data['starting_retirement_minor'] ?? 0) > 0
        ) {
            try {
                $this->buckets->applyDeltas(
                    $userId,
                    (int) $fund->id,
                    (int) $data['starting_vested_minor'],
                    (int) $data['starting_savings_minor'],
                    (int) $data['starting_retirement_minor'],
                    now()->toDateString(),
                );
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        if (($data['provident_vested_pre2021_minor'] ?? 0) > 0) {
            $bucket->provident_vested_pre2021_balance_minor = (int) $data['provident_vested_pre2021_minor'];
            $bucket->save();
        }

        return response()->json(['data' => new ZaRetirementFundResource($fund->fresh())], 201);
    }

    public function showBuckets(Request $request, int $fundId): JsonResponse
    {
        $owns = DCPension::query()
            ->where('id', $fundId)
            ->where('user_id', $request->user()->id)
            ->where('country_code', 'ZA')
            ->exists();

        if (! $owns) {
            return response()->json(['message' => 'Fund not found'], 404);
        }

        $bucket = $this->buckets->findOrCreate($request->user()->id, $fundId);

        return response()->json(['data' => new ZaRetirementBucketResource($bucket)]);
    }

    public function storeContribution(StoreContributionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        $owns = DCPension::query()
            ->where('id', $data['fund_holding_id'])
            ->where('user_id', $userId)
            ->where('country_code', 'ZA')
            ->exists();
        if (! $owns) {
            return response()->json(['message' => 'Fund not found'], 404);
        }

        try {
            $split = $this->splitter->split((int) $data['amount_minor'], $data['contribution_date']);
            $bucket = $this->buckets->applyDeltas(
                $userId,
                (int) $data['fund_holding_id'],
                $split['vested_delta_minor'],
                $split['savings_delta_minor'],
                $split['retirement_delta_minor'],
                $data['contribution_date'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'split' => [
                    'vested_minor' => $split['vested_delta_minor'],
                    'savings_minor' => $split['savings_delta_minor'],
                    'retirement_minor' => $split['retirement_delta_minor'],
                ],
                'buckets' => new ZaRetirementBucketResource($bucket),
            ],
        ], 201);
    }

    public function simulateSavingsPotWithdrawal(SimulateSavingsPotWithdrawalRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->simulator->simulate(
                (int) $data['amount_minor'],
                (int) $data['current_annual_income_minor'],
                (int) $data['age'],
                $data['tax_year'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function withdrawSavingsPot(SimulateSavingsPotWithdrawalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        $owns = DCPension::query()
            ->where('id', $data['fund_holding_id'])
            ->where('user_id', $userId)
            ->where('country_code', 'ZA')
            ->exists();
        if (! $owns) {
            return response()->json(['message' => 'Fund not found'], 404);
        }

        try {
            $sim = $this->simulator->simulate(
                (int) $data['amount_minor'],
                (int) $data['current_annual_income_minor'],
                (int) $data['age'],
                $data['tax_year'],
            );
            $bucket = $this->buckets->applyDeltas(
                $userId,
                (int) $data['fund_holding_id'],
                0,
                -1 * (int) $data['amount_minor'],
                0,
                now()->toDateString(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'withdrawal' => [
                    'gross_minor' => (int) $data['amount_minor'],
                    'tax_minor' => $sim['tax_delta_minor'],
                    'net_minor' => $sim['net_received_minor'],
                ],
                'buckets' => new ZaRetirementBucketResource($bucket),
            ],
        ], 201);
    }

    public function calculateTaxRelief(CalculateTaxReliefRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var ZaRetirementEngine $engine */
        $engine = app('pack.za.retirement');

        $result = $engine->calculatePensionTaxRelief(
            (int) $data['contribution_minor'],
            (int) $data['gross_income_minor'],
            $data['tax_year'],
        );

        return response()->json([
            'data' => [
                'relief_amount_minor' => (int) $result['relief_amount'],
                'relief_rate' => (float) $result['relief_rate'],
                'net_cost_minor' => (int) $result['net_cost'],
                'method' => (string) $result['method'],
                'tax_year' => $data['tax_year'],
            ],
        ]);
    }

    public function quoteLivingAnnuity(LivingAnnuityQuoteRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->livingAnnuity->calculate(
                (int) $data['capital_minor'],
                (int) $data['drawdown_rate_bps'],
                (int) $data['age'],
                $data['tax_year'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $result['kind'] = 'living';
        $result['tax_year'] = $data['tax_year'];
        $result['capital_minor'] = (int) $data['capital_minor'];

        return response()->json(['data' => new ZaAnnuityQuoteResource($result)]);
    }

    public function quoteLifeAnnuity(LifeAnnuityQuoteRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->lifeAnnuity->calculate(
                (int) $data['annual_annuity_minor'],
                (int) $data['declared_section_10c_pool_minor'],
                (int) $data['age'],
                $data['tax_year'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $result['kind'] = 'life';
        $result['tax_year'] = $data['tax_year'];
        $result['annual_annuity_minor'] = (int) $data['annual_annuity_minor'];

        return response()->json(['data' => new ZaAnnuityQuoteResource($result)]);
    }

    public function apportionCompulsory(CompulsoryApportionRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->compulsory->apportion(
                (int) $data['vested_minor'],
                (int) $data['provident_vested_pre2021_minor'],
                (int) $data['retirement_minor'],
                $data['tax_year'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'pcls_minor' => (int) $result['pcls_minor'],
                'compulsory_annuity_minor' => (int) $result['compulsory_annuity_minor'],
                'de_minimis_applied' => (bool) $result['de_minimis_applied'],
                'de_minimis_threshold_minor' => (int) $result['de_minimis_threshold_minor'],
                'tax_year' => $data['tax_year'],
            ],
        ]);
    }

    public function checkReg28(Reg28CheckRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->reg28->check($data['allocation'], $data['tax_year']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'compliant' => $result['compliant'],
                'breaches' => $result['breaches'],
                'per_class' => $result['per_class'],
                'tax_year' => $data['tax_year'],
            ],
        ]);
    }

    public function listReg28Snapshots(Request $request): JsonResponse
    {
        $query = ZaReg28Snapshot::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('as_at_date');

        if ($taxYear = $request->query('tax_year')) {
            [$startYear] = explode('/', (string) $taxYear);
            $startYear = (int) $startYear;
            $start = sprintf('%04d-03-01', $startYear);
            $end = sprintf('%04d-02-28', $startYear + 1);
            $query->whereBetween('as_at_date', [$start, $end]);
        }

        return response()->json(['data' => Reg28SnapshotResource::collection($query->get())]);
    }

    public function storeReg28Snapshot(Reg28CheckRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        if (! empty($data['fund_holding_id'])) {
            $owns = DCPension::query()
                ->where('id', $data['fund_holding_id'])
                ->where('user_id', $userId)
                ->where('country_code', 'ZA')
                ->exists();
            if (! $owns) {
                return response()->json(['message' => 'Fund not found'], 404);
            }
        }

        try {
            $snapshot = $this->reg28->snapshot(
                $userId,
                isset($data['fund_holding_id']) ? (int) $data['fund_holding_id'] : null,
                $data['allocation'],
                now()->toDateString(),
                $data['tax_year'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new Reg28SnapshotResource($snapshot)], 201);
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
