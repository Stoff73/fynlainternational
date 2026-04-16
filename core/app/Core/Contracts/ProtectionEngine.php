<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Insurance and protection product contract for a jurisdiction.
 *
 * Defines available policy types, coverage needs calculation,
 * and tax treatment of insurance products. All monetary values
 * are in minor currency units.
 */
interface ProtectionEngine
{
    /**
     * Get all available protection/insurance policy types for the jurisdiction.
     *
     * @return array<int, array{
     *     code: string,
     *     name: string,
     *     description: string,
     *     category: string
     * }> List of policy types with codes, display names, and category (life, income, health, etc.)
     */
    public function getAvailablePolicyTypes(): array;

    /**
     * Calculate recommended coverage amounts based on the individual's circumstances.
     *
     * @param array{
     *     policy_type: string,
     *     annual_income: int,
     *     outstanding_debts: int,
     *     dependants: int,
     *     existing_coverage: int,
     *     age: int,
     *     retirement_age?: int
     * } $params All monetary values in minor currency units
     *
     * @return array{
     *     recommended_cover: int,
     *     minimum_cover: int,
     *     shortfall: int,
     *     rationale: string
     * } Coverage amounts in minor currency units with explanatory rationale
     */
    public function calculateCoverageNeeds(array $params): array;

    /**
     * Get the tax treatment of a specific policy type (premiums, payouts, trusts).
     *
     * @param string $policyType Policy type code
     *
     * @return array{
     *     premiums_deductible: bool,
     *     payout_taxable: bool,
     *     trust_treatment: string,
     *     notes: string
     * } Tax treatment details for premiums and payouts
     */
    public function getPolicyTaxTreatment(string $policyType): array;
}
