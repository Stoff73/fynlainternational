<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Http\Controllers;

use Fynla\Core\Http\Controller;
use Fynla\Packs\Za\Goals\ZaGoalsDefaults;
use Fynla\Packs\Za\Goals\ZaSeveranceBenefitCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP adapter over the ZA pack's goals domain (SA Goals v1).
 *
 * Thin proxy: delegates to ZaGoalsDefaults (SA-appropriate goal defaults) and
 * ZaSeveranceBenefitCalculator (retrenchment lump-sum tax). No business logic
 * here — the pack owns the SARS rules. Mirrors ZaEstateController.
 */
class ZaGoalsController extends Controller
{
    public function __construct(
        private readonly ZaGoalsDefaults $defaults,
        private readonly ZaSeveranceBenefitCalculator $severance,
    ) {}

    /**
     * SA-appropriate goal defaults (bond, tuition, severance threshold) for
     * the active/queried tax year — used to seed SA goal creation.
     */
    public function defaults(Request $request): JsonResponse
    {
        $taxYear = (string) $request->query('tax_year', $this->currentZaTaxYear());

        return response()->json([
            'success' => true,
            'tax_year' => $taxYear,
            'data' => [
                'bond' => $this->defaults->getBondDefaults($taxYear),
                'tuition' => $this->defaults->getTuitionDefaults($taxYear),
                'severance_tax_free_threshold_minor' => $this->defaults->getSeveranceTaxFreeThresholdMinor($taxYear),
            ],
        ]);
    }

    /**
     * Tax on a severance / retrenchment lump sum (SARS retirement table).
     */
    public function severanceBenefit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'severance_amount_minor' => 'required|integer|min:0|max:10000000000000',
            'prior_cumulative_lump_sum_minor' => 'nullable|integer|min:0|max:10000000000000',
            'tax_year' => 'nullable|string|regex:/^\d{4}\/\d{2}$/',
        ]);
        $taxYear = (string) ($validated['tax_year'] ?? $this->currentZaTaxYear());

        $result = $this->severance->calculate(
            (int) $validated['severance_amount_minor'],
            (int) ($validated['prior_cumulative_lump_sum_minor'] ?? 0),
            $taxYear,
        );

        return response()->json([
            'success' => true,
            'tax_year' => $taxYear,
            'data' => $result,
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
