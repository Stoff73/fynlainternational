<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Savings calculation contract for a jurisdiction.
 *
 * All monetary values are expressed in minor currency units (pence / cents)
 * to avoid floating-point rounding errors. Methods that are jurisdiction-
 * specific (e.g. TFSA penalty in SA, ISA over-subscription in UK) share a
 * uniform return shape via the $annualPriorMinor + $lifetimePriorMinor
 * pair — implementations that don't use lifetime caps return
 * getLifetimeContributionCap() = null.
 */
interface SavingsEngine
{
    /**
     * Compute income-tax liability on interest receipts after any
     * jurisdiction-specific exemption (SA: age-indexed interest exemption;
     * UK: personal savings allowance + starting rate). Marginal-rate delta
     * is resolved against the caller-supplied other taxable income.
     *
     * @return array{
     *     taxable_interest_minor: int,
     *     exemption_applied_minor: int,
     *     tax_due_minor: int,
     *     marginal_rate: float
     * }
     */
    public function calculateInterestTax(
        int $interestMinor,
        int $otherTaxableIncomeMinor,
        int $age,
        string $taxYear,
    ): array;

    /**
     * Score a tax-free wrapper contribution against that jurisdiction's
     * annual / lifetime caps. Returns any excess + penalty.
     *
     * SA TFSA: 40% flat penalty on excess over annual or lifetime cap.
     * UK ISA: excess income is taxed rather than a flat penalty — the UK
     * implementation returns penalty_minor = 0 with excess_minor populated
     * so callers can route excess into the normal income-tax path.
     *
     * @return array{
     *     penalty_minor: int,
     *     excess_minor: int,
     *     breached_cap: ?string,
     *     annual_remaining_minor: int,
     *     lifetime_remaining_minor: int
     * }
     */
    public function calculateTaxFreeWrapperPenalty(
        int $contributionMinor,
        int $annualPriorMinor,
        int $lifetimePriorMinor,
        string $taxYear,
    ): array;

    /**
     * Annual contribution cap for the jurisdiction's tax-free savings
     * wrapper. UK ISA: currently £20,000. SA TFSA: currently R46,000.
     */
    public function getAnnualContributionCap(string $taxYear): int;

    /**
     * Lifetime contribution cap. SA TFSA: R500,000. UK ISA: null (no
     * lifetime cap).
     */
    public function getLifetimeContributionCap(string $taxYear): ?int;

    /**
     * Compute the emergency-fund target for a household.
     *
     * $context is intentionally an associative array rather than a typed
     * DTO — the inputs differ materially per jurisdiction:
     *   - UK: ['income_stability' => 'stable'|'volatile']
     *   - SA: ['income_stability' => 'stable'|'volatile',
     *          'household_income_earners' => int,
     *          'uif_eligible' => bool]
     * Implementations read only the keys they need and tolerate extras.
     *
     * @param array<string, mixed> $context
     *
     * @return array{
     *     target_months: int,
     *     target_minor: int,
     *     weighting_reason: string
     * }
     */
    public function calculateEmergencyFundTarget(
        int $essentialMonthlyExpenditureMinor,
        array $context,
        string $taxYear,
    ): array;
}
