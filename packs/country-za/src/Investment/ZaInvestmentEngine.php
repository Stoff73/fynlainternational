<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Investment;

use Fynla\Core\Contracts\InvestmentEngine;
use Fynla\Packs\Za\Savings\ZaSavingsEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use InvalidArgumentException;

/**
 * ZA InvestmentEngine. Routes tax composition by wrapper:
 *
 *   tfsa          → all tax suppressed (wrapper is tax-free)
 *   endowment     → 30% flat rate inside wrapper (no exclusion)
 *   discretionary → 40% inclusion × marginal rate + R40k exclusion +
 *                   20% DWT + interest exemption path via ZaSavingsEngine
 *
 * Pure calculator. Delegates to ZaCgtCalculator and ZaSavingsEngine.
 */
class ZaInvestmentEngine implements InvestmentEngine
{
    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaCgtCalculator $cgt,
        private readonly ZaSavingsEngine $savings,
    ) {
    }

    public function getTaxWrappers(): array
    {
        return [
            [
                'code' => 'tfsa',
                'name' => 'Tax-Free Savings Account',
                'description' => 'Tax-free wrapper with annual and lifetime caps',
                'tax_treatment' => 'No income tax, CGT, or dividend withholding',
            ],
            [
                'code' => 'discretionary',
                'name' => 'Discretionary portfolio',
                'description' => 'Unwrapped unit trusts, ETFs, direct equities',
                'tax_treatment' => 'Interest at marginal rate (after exemption); 40% CGT inclusion; 20% local DWT',
            ],
            [
                'code' => 'endowment',
                'name' => 'Endowment (section 29A)',
                'description' => '5-year restriction wrapper for higher-rate taxpayers',
                'tax_treatment' => '30% flat rate inside wrapper for income and CGT',
            ],
        ];
    }

    public function getAnnualAllowances(string $taxYear): array
    {
        return [
            'tfsa' => $this->savings->getAnnualContributionCap($taxYear),
            'discretionary' => PHP_INT_MAX,
            'endowment' => PHP_INT_MAX,
        ];
    }

    public function calculateInvestmentTax(array $params): array
    {
        $wrapper = $params['wrapper_code'] ?? '';
        $gains = (int) ($params['gains'] ?? 0);
        $dividends = (int) ($params['dividends'] ?? 0);
        $interest = (int) ($params['interest'] ?? 0);
        $taxYear = (string) ($params['tax_year'] ?? '');
        $income = (int) ($params['income_minor'] ?? 0);
        $age = (int) ($params['age'] ?? 40);

        if ($taxYear === '') {
            throw new InvalidArgumentException('tax_year is required.');
        }

        return match ($wrapper) {
            'tfsa' => $this->zeroTaxBreakdown($wrapper),
            'endowment' => $this->endowmentBreakdown($gains, $taxYear),
            'discretionary' => $this->discretionaryBreakdown(
                $gains, $dividends, $interest, $income, $age, $taxYear,
            ),
            default => throw new InvalidArgumentException(
                "Unknown wrapper_code: '{$wrapper}'. Must be one of: tfsa, discretionary, endowment.",
            ),
        };
    }

    public function getAssetAllocationRules(): array
    {
        // Retirement-fund allocation rules live on the retirement engine
        // (WS 1.4). Discretionary / endowment / TFSA wrappers have no
        // regulatory allocation limits.
        return [];
    }

    /**
     * @return array{total_tax: int, gains_tax: int, dividend_tax: int, interest_tax: int, breakdown: array<string, mixed>}
     */
    private function zeroTaxBreakdown(string $wrapper): array
    {
        return [
            'total_tax' => 0,
            'gains_tax' => 0,
            'dividend_tax' => 0,
            'interest_tax' => 0,
            'breakdown' => [
                'wrapper_code' => $wrapper,
                'note' => 'Tax-free wrapper — no income, CGT, or DWT liability.',
            ],
        ];
    }

    /**
     * @return array{total_tax: int, gains_tax: int, dividend_tax: int, interest_tax: int, breakdown: array<string, mixed>}
     */
    private function endowmentBreakdown(int $gains, string $taxYear): array
    {
        $r = $this->cgt->calculateEndowmentCgt($gains, $taxYear);

        return [
            'total_tax' => $r['tax_due_minor'],
            'gains_tax' => $r['tax_due_minor'],
            'dividend_tax' => 0,
            'interest_tax' => 0,
            'breakdown' => [
                'wrapper_code' => 'endowment',
                'wrapper_rate_bps' => $r['wrapper_rate_bps'],
            ],
        ];
    }

    /**
     * @return array{total_tax: int, gains_tax: int, dividend_tax: int, interest_tax: int, breakdown: array<string, mixed>}
     */
    private function discretionaryBreakdown(
        int $gains,
        int $dividends,
        int $interest,
        int $income,
        int $age,
        string $taxYear,
    ): array {
        $cgtResult = $this->cgt->calculateDiscretionaryCgt($gains, $income, $age, $taxYear);
        $gainsTax = $cgtResult['tax_due_minor'];

        $dwtBps = (int) $this->config->get($taxYear, 'dwt.local_rate_bps', 0);
        $dividendTax = $dividends > 0 ? (int) round($dividends * $dwtBps / 10_000) : 0;

        $interestResult = $this->savings->calculateInterestTax($interest, $income, $age, $taxYear);
        $interestTax = $interestResult['tax_due_minor'];

        return [
            'total_tax' => $gainsTax + $dividendTax + $interestTax,
            'gains_tax' => $gainsTax,
            'dividend_tax' => $dividendTax,
            'interest_tax' => $interestTax,
            'breakdown' => [
                'wrapper_code' => 'discretionary',
                'cgt' => $cgtResult,
                'dwt_rate_bps' => $dwtBps,
                'interest' => $interestResult,
            ],
        ];
    }
}
