<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Za;

use App\Http\Controllers\Controller;
use App\Http\Requests\Za\ExchangeControl\CheckApprovalRequest;
use App\Http\Requests\Za\ExchangeControl\StoreTransferRequest;
use App\Http\Resources\Za\ZaExchangeControlEntryResource;
use Fynla\Core\Contracts\ExchangeControl;
use Fynla\Core\Money\Currency;
use Fynla\Core\Money\Money;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger;
use Fynla\Packs\Za\Models\ZaExchangeControlEntry;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * HTTP adapter over the ZA pack's exchange control domain (WS 1.3c).
 *
 * Calendar-year keyed (NOT tax-year). Resolves pack.za.exchange_control
 * + pack.za.exchange_control.ledger via container.
 *
 * Internal arithmetic in dashboard() uses Money VO per ADR-005; wire
 * format stays integer minor units.
 */
class ZaExchangeControlController extends Controller
{
    public function __construct(
        private readonly ZaExchangeControlLedger $ledger,
        private readonly ZaTaxConfigService $config,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $year = (int) $request->query('calendar_year', date('Y'));

        /** @var ExchangeControl $engine */
        $engine = app('pack.za.exchange_control');

        $allowances = $engine->getAnnualAllowances();

        $sdaConsumedMinor = $this->ledger->sumConsumed($request->user()->id, $year, 'sda');
        $fiaConsumedMinor = $this->ledger->sumConsumed($request->user()->id, $year, 'fia');

        $sdaCap = (int) ($allowances['sda']['annual_limit'] ?? 0);
        $fiaCap = (int) ($allowances['fia']['annual_limit'] ?? 0);

        $zar = Currency::ZAR();
        $sdaCapMoney = new Money($sdaCap, $zar);
        $fiaCapMoney = new Money($fiaCap, $zar);
        $sdaConsumed = new Money($sdaConsumedMinor, $zar);
        $fiaConsumed = new Money($fiaConsumedMinor, $zar);

        $sdaRemaining = $sdaCapMoney->minus($sdaConsumed);
        if ($sdaRemaining->isNegative()) {
            $sdaRemaining = new Money(0, $zar);
        }
        $fiaRemaining = $fiaCapMoney->minus($fiaConsumed);
        if ($fiaRemaining->isNegative()) {
            $fiaRemaining = new Money(0, $zar);
        }

        $sarbThreshold = (int) $this->config->get('2026/27', 'excon.sarb_special_approval_threshold_minor', 0);

        return response()->json([
            'data' => [
                'calendar_year' => $year,
                'allowances' => $allowances,
                'consumed' => [
                    'sda_minor' => $sdaConsumed->minor,
                    'fia_minor' => $fiaConsumed->minor,
                    'total_minor' => $sdaConsumed->plus($fiaConsumed)->minor,
                ],
                'remaining' => [
                    'sda_minor' => $sdaRemaining->minor,
                    'fia_minor' => $fiaRemaining->minor,
                ],
                'sarb_threshold_minor' => $sarbThreshold,
            ],
        ]);
    }

    public function listTransfers(Request $request): JsonResponse
    {
        $year = (int) $request->query('calendar_year', date('Y'));

        $entries = ZaExchangeControlEntry::query()
            ->where('user_id', $request->user()->id)
            ->where('calendar_year', $year)
            ->orderByDesc('transfer_date')
            ->get();

        return response()->json(['data' => ZaExchangeControlEntryResource::collection($entries)]);
    }

    public function storeTransfer(StoreTransferRequest $request): JsonResponse
    {
        $data = $request->validated();
        $year = (int) date('Y', strtotime($data['transfer_date']));

        try {
            $id = $this->ledger->record(
                userId: $request->user()->id,
                calendarYear: $year,
                allowanceType: $data['allowance_type'],
                amountMinor: (int) $data['amount_minor'],
                transferDate: $data['transfer_date'],
                destinationCountry: $data['destination_country'] ?? null,
                purpose: $data['purpose'] ?? null,
                authorisedDealer: $data['authorised_dealer'] ?? null,
                recipientAccount: $data['recipient_account'] ?? null,
                aitReference: $data['ait_reference'] ?? null,
                aitDocuments: $data['ait_documents'] ?? null,
                notes: $data['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'id' => $id,
                'calendar_year' => $year,
            ],
        ], 201);
    }

    public function checkApproval(CheckApprovalRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var ExchangeControl $engine */
        $engine = app('pack.za.exchange_control');

        return response()->json([
            'data' => [
                'requires_approval' => $engine->requiresApproval((int) $data['amount_minor'], $data['type']),
                'amount_minor' => (int) $data['amount_minor'],
                'type' => $data['type'],
            ],
        ]);
    }
}
