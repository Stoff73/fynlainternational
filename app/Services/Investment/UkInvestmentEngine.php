<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Services\TaxConfigService;
use Fynla\Core\Contracts\InvestmentEngine;

/**
 * UK-side InvestmentEngine implementation.
 *
 * Exposes UK tax wrappers (ISA, GIA) and their annual allowances behind
 * the jurisdiction-uniform contract. Tax calculations are stubbed for
 * WS 1.3a — callers who need full UK investment tax composition keep
 * using the existing UK services directly until a follow-up lifts that
 * logic into the engine.
 */
class UkInvestmentEngine implements InvestmentEngine
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {
    }

    public function getTaxWrappers(): array
    {
        return [
            [
                'code' => 'isa',
                'name' => 'Individual Savings Account',
                'description' => 'Tax-free wrapper for cash, stocks, and lifetime ISAs',
                'tax_treatment' => 'Tax-free growth and withdrawals',
            ],
            [
                'code' => 'gia',
                'name' => 'General Investment Account',
                'description' => 'Unwrapped discretionary portfolio',
                'tax_treatment' => 'CGT on disposal; dividend allowance; PSA on interest',
            ],
        ];
    }

    public function getAnnualAllowances(string $taxYear): array
    {
        $isa = $this->taxConfig->getISAAllowances();
        $isaPounds = (int) ($isa['annual_allowance'] ?? 20_000);

        return [
            'isa' => $isaPounds * 100,
            'gia' => PHP_INT_MAX,
        ];
    }

    public function calculateInvestmentTax(array $params): array
    {
        return [
            'total_tax' => 0,
            'gains_tax' => 0,
            'dividend_tax' => 0,
            'interest_tax' => 0,
            'breakdown' => [
                'note' => 'UK investment tax composition deferred to a follow-up; callers should use the existing UK services directly in the interim.',
            ],
        ];
    }

    public function getAssetAllocationRules(): array
    {
        return [];
    }
}
