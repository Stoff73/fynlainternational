<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Http\Controllers;

use Fynla\Core\Http\Controller;
use Fynla\Packs\Za\Estate\ZaEstateEngine;
use Fynla\Packs\Za\Http\Requests\Estate\EstateSummaryRequest;
use Fynla\Packs\Za\Http\Requests\Estate\StoreZaDonationRequest;
use Fynla\Packs\Za\Models\ZaDonation;
use Fynla\Packs\Za\Tax\ZaTaxEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP adapter over the ZA pack's estate domain (SA Estate v1, slice 1).
 *
 * Thin proxy: delegates to ZaEstateEngine (bound pack.za.estate). No business
 * logic here — the pack owns the SARS estate-duty / CGT-on-death math, the app
 * owns HTTP + auth + validation. Mirrors ZaSavingsController.
 */
class ZaEstateController extends Controller
{
    public function __construct(
        private readonly ZaEstateEngine $engine,
        private readonly ZaTaxEngine $taxEngine,
    ) {}

    /**
     * Estate-duty summary from the supplied position (all int minor units).
     */
    public function summary(EstateSummaryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $taxYear = (string) ($data['tax_year'] ?? $this->currentZaTaxYear());

        $result = $this->engine->calculateEstateTax([
            'gross_estate' => (int) $data['gross_estate_minor'],
            'liabilities' => (int) ($data['liabilities_minor'] ?? 0),
            'spouse_transfer' => (int) ($data['spouse_transfer_minor'] ?? 0),
            'exempt_transfers' => (int) ($data['exempt_transfers_minor'] ?? 0),
            'has_predeceased_spouse' => (bool) ($data['has_predeceased_spouse'] ?? false),
            'prior_spousal_abatement_used_minor' => (int) ($data['prior_spousal_abatement_used_minor'] ?? 0),
        ], $taxYear);

        return response()->json([
            'success' => true,
            'data' => $result,
            'tax_year' => $taxYear,
        ]);
    }

    /**
     * The exemptions and reliefs reference for the active tax year.
     */
    public function exemptions(Request $request): JsonResponse
    {
        $taxYear = (string) $request->query('tax_year', $this->currentZaTaxYear());

        return response()->json([
            'success' => true,
            'data' => [
                'exemptions' => $this->engine->getExemptions($taxYear),
                'reliefs' => $this->engine->getReliefs(),
            ],
            'tax_year' => $taxYear,
        ]);
    }

    /**
     * Capital-gains tax arising on death from a deemed disposal.
     */
    public function cgtOnDeath(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deemed_gain_minor' => 'required|integer|min:0|max:10000000000000',
            'other_taxable_income_minor' => 'nullable|integer|min:0|max:10000000000000',
            'tax_year' => 'nullable|string|regex:/^\d{4}\/\d{2}$/',
        ]);
        $taxYear = (string) ($validated['tax_year'] ?? $this->currentZaTaxYear());

        $result = $this->engine->calculateCgtOnDeath(
            (int) $validated['deemed_gain_minor'],
            (int) ($validated['other_taxable_income_minor'] ?? 0),
            $taxYear,
        );

        return response()->json([
            'success' => true,
            'data' => $result,
            'tax_year' => $taxYear,
        ]);
    }

    /**
     * List the authenticated user's donation register (most recent first).
     */
    public function donations(Request $request): JsonResponse
    {
        $donations = ZaDonation::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('donation_date')
            ->limit(500)
            ->get(['id', 'amount_minor', 'amount_ccy', 'donation_date', 'recipient', 'is_exempt', 'notes']);

        return response()->json(['success' => true, 'data' => $donations]);
    }

    /**
     * Record a donation and return the SARS donations-tax position it creates.
     */
    public function storeDonation(StoreZaDonationRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $donation = ZaDonation::create([
            'user_id' => $user->id,
            'amount_minor' => (int) $data['amount_minor'],
            'amount_ccy' => 'ZAR',
            'donation_date' => $data['donation_date'],
            'recipient' => $data['recipient'] ?? null,
            'is_exempt' => (bool) ($data['is_exempt'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $donation->only(['id', 'amount_minor', 'amount_ccy', 'donation_date', 'recipient', 'is_exempt', 'notes']),
            'donations_tax' => $this->computeDonationsTax($user->id),
        ], 201);
    }

    /**
     * Delete a donation the caller owns.
     */
    public function deleteDonation(Request $request, int $id): JsonResponse
    {
        $deleted = ZaDonation::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['success' => false, 'message' => 'Donation not found.'], 404);
        }

        return response()->json(['success' => true]);
    }

    /**
     * The user's current SARS donations-tax position from the register.
     */
    public function donationsTax(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->computeDonationsTax($request->user()->id),
        ]);
    }

    /**
     * Aggregate the register (non-exempt, since the SARS anchor) and delegate
     * to the tax engine. this_year = donations in the current SA tax year;
     * cumulative_before = everything since the anchor up to this tax year.
     *
     * @return array{tax_due_minor: int, annual_exemption_used_minor: int, cumulative_after_minor: int, this_year_minor: int, cumulative_before_minor: int}
     */
    private function computeDonationsTax(int $userId): array
    {
        $taxYear = $this->currentZaTaxYear();
        $yearStart = $this->zaTaxYearStart($taxYear);

        $base = ZaDonation::query()
            ->where('user_id', $userId)
            ->where('is_exempt', false)
            ->whereDate('donation_date', '>=', ZaDonation::CUMULATIVE_ANCHOR);

        $thisYear = (int) (clone $base)->whereDate('donation_date', '>=', $yearStart)->sum('amount_minor');
        $cumulativeBefore = (int) (clone $base)->whereDate('donation_date', '<', $yearStart)->sum('amount_minor');

        $result = $this->taxEngine->calculateDonationsTax($thisYear, $taxYear, $cumulativeBefore);

        return array_merge($result, [
            'this_year_minor' => $thisYear,
            'cumulative_before_minor' => $cumulativeBefore,
        ]);
    }

    /** First day of the SA tax year "YYYY/YY" (1 March of the start year). */
    private function zaTaxYearStart(string $taxYear): string
    {
        $startYear = (int) substr($taxYear, 0, 4);

        return sprintf('%d-03-01', $startYear);
    }

    /**
     * Current SA tax year (SARS runs 1 March – end February) as "YYYY/YY".
     */
    private function currentZaTaxYear(): string
    {
        $now = now();
        $startYear = $now->month >= 3 ? $now->year : $now->year - 1;

        return sprintf('%d/%02d', $startYear, ($startYear + 1) % 100);
    }
}
