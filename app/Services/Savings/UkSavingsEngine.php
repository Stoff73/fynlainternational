<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Services\TaxConfigService;
use Fynla\Core\Contracts\SavingsEngine;

/**
 * UK-side SavingsEngine implementation.
 *
 * Wraps existing UK services (TaxConfigService, ISATracker,
 * EmergencyFundCalculator) behind the SavingsEngine contract so
 * jurisdiction-aware callers can resolve `pack.gb.savings` and
 * `pack.za.savings` uniformly.
 *
 * Methods that have no UK equivalent return safe defaults:
 *   - getLifetimeContributionCap() → null (UK ISA has no lifetime cap)
 *   - calculateTaxFreeWrapperPenalty() → penalty_minor = 0 (UK ISA
 *     excess is taxed rather than penalised; callers route the
 *     excess_minor into the normal income-tax path)
 *
 * calculateInterestTax is stubbed for Phase 1 — the full UK PSA +
 * starting-rate marginal path lives in TaxConfigService + module
 * services. Lifting it into the engine is a future enhancement; the
 * stub returns zero tax due with the interest passed through as taxable.
 */
class UkSavingsEngine implements SavingsEngine
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {
    }

    public function calculateInterestTax(
        int $interestMinor,
        int $otherTaxableIncomeMinor,
        int $age,
        string $taxYear,
    ): array {
        return [
            'taxable_interest_minor' => max(0, $interestMinor),
            'exemption_applied_minor' => 0,
            'tax_due_minor' => 0,
            'marginal_rate' => 0.0,
        ];
    }

    public function calculateTaxFreeWrapperPenalty(
        int $contributionMinor,
        int $annualPriorMinor,
        int $lifetimePriorMinor,
        string $taxYear,
    ): array {
        $annualCap = $this->getAnnualContributionCap($taxYear);
        $annualAfter = $annualPriorMinor + $contributionMinor;
        $annualExcess = max(0, $annualAfter - $annualCap);

        return [
            'penalty_minor' => 0,
            'excess_minor' => $annualExcess,
            'breached_cap' => $annualExcess > 0 ? 'annual' : null,
            'annual_remaining_minor' => max(0, $annualCap - $annualAfter),
            'lifetime_remaining_minor' => PHP_INT_MAX,
        ];
    }

    public function getAnnualContributionCap(string $taxYear): int
    {
        $isa = $this->taxConfig->getISAAllowances();
        $allowancePounds = (int) ($isa['annual_allowance'] ?? 20_000);

        return $allowancePounds * 100;
    }

    public function getLifetimeContributionCap(string $taxYear): ?int
    {
        return null;
    }

    public function calculateEmergencyFundTarget(
        int $essentialMonthlyExpenditureMinor,
        array $context,
        string $taxYear,
    ): array {
        $stability = $context['income_stability'] ?? 'stable';
        $targetMonths = $stability === 'volatile' ? 6 : 3;

        return [
            'target_months' => $targetMonths,
            'target_minor' => $targetMonths * $essentialMonthlyExpenditureMinor,
            'weighting_reason' => $stability === 'volatile' ? 'volatile_income' : 'baseline',
        ];
    }
}
