<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Http\Controllers;

use Fynla\Core\Http\Controller;
use Fynla\Packs\Za\Estate\ZaEstateEngine;
use Fynla\Packs\Za\Http\Requests\Estate\EstateSummaryRequest;
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
     * Current SA tax year (SARS runs 1 March – end February) as "YYYY/YY".
     */
    private function currentZaTaxYear(): string
    {
        $now = now();
        $startYear = $now->month >= 3 ? $now->year : $now->year - 1;

        return sprintf('%d/%02d', $startYear, ($startYear + 1) % 100);
    }
}
