<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Savings calculation contract for a jurisdiction.
 *
 * All monetary values are expressed in minor currency units (pence / cents)
 * to avoid floating-point rounding errors. Methods exposing a tax-free
 * wrapper allow for both annual-only and annual+lifetime cap regimes
 * through a uniform return shape — implementations that don't use
 * lifetime caps return getLifetimeContributionCap() = null.
 */
interface SavingsEngine
{
    /**
     * Compute income-tax liability on interest receipts after any
     * jurisdiction-specific exemption. Marginal-rate delta is resolved
     * against the caller-supplied other taxable income.
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
     * Implementations that apply a flat-rate penalty on excess contributions
     * return penalty_minor populated. Implementations that tax the excess
     * under the ordinary income-tax path instead return penalty_minor = 0
     * with excess_minor populated so callers can route through income tax.
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
     * wrapper, in minor currency units.
     */
    public function getAnnualContributionCap(string $taxYear): int;

    /**
     * Lifetime contribution cap in minor currency units, or null when the
     * jurisdiction imposes no lifetime cap.
     */
    public function getLifetimeContributionCap(string $taxYear): ?int;

    /**
     * Compute the emergency-fund target for a household.
     *
     * $context is intentionally an associative array rather than a typed
     * DTO — the inputs differ materially per jurisdiction. Implementations
     * declare which keys they read and tolerate additional keys.
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
