<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Savings;

use Fynla\Core\Contracts\SavingsEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;
use InvalidArgumentException;

/**
 * SARS 2026/27 savings engine for South Africa. Implements the core
 * SavingsEngine contract.
 *
 * Pure calculator. No DB access. Consumes ZaTaxEngine for marginal
 * income-tax composition and ZaTaxConfigService for static caps
 * (TFSA R46k/R500k, 40% penalty, interest exemptions R23,800 / R34,500).
 *
 * References:
 *   - Plans/SA_Research_and_Mapping.md § 7
 *   - April/April18Updates/PRD-ws-1-2a-za-savings-tfsa.md
 *   - ADR-005 (Money VO / minor units)
 */
class ZaSavingsEngine implements SavingsEngine
{
    private const AGE_EXEMPTION_BREAK = 65;
    private const BASELINE_MONTHS = 3;
    private const SINGLE_OR_VOLATILE_MONTHS = 6;
    private const UIF_INELIGIBLE_BUMP_MONTHS = 1;

    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    public function calculateInterestTax(
        int $interestMinor,
        int $otherTaxableIncomeMinor,
        int $age,
        string $taxYear,
    ): array {
        if ($interestMinor < 0 || $otherTaxableIncomeMinor < 0 || $age < 0) {
            throw new InvalidArgumentException('Interest calc inputs cannot be negative.');
        }

        $exemption = $age >= self::AGE_EXEMPTION_BREAK
            ? (int) $this->config->get($taxYear, 'interest.exemption_65_plus_minor', 0)
            : (int) $this->config->get($taxYear, 'interest.exemption_under_65_minor', 0);

        $exemptionApplied = min($interestMinor, $exemption);
        $taxableInterest = $interestMinor - $exemptionApplied;

        if ($taxableInterest === 0) {
            return [
                'taxable_interest_minor' => 0,
                'exemption_applied_minor' => $exemptionApplied,
                'tax_due_minor' => 0,
                'marginal_rate' => 0.0,
            ];
        }

        $baseline = $this->taxEngine->calculateIncomeTaxForAge(
            $otherTaxableIncomeMinor,
            $taxYear,
            $age,
        );
        $withInterest = $this->taxEngine->calculateIncomeTaxForAge(
            $otherTaxableIncomeMinor + $taxableInterest,
            $taxYear,
            $age,
        );

        return [
            'taxable_interest_minor' => $taxableInterest,
            'exemption_applied_minor' => $exemptionApplied,
            'tax_due_minor' => max(0, $withInterest['tax_due'] - $baseline['tax_due']),
            'marginal_rate' => (float) $withInterest['marginal_rate'],
        ];
    }

    public function calculateTaxFreeWrapperPenalty(
        int $contributionMinor,
        int $annualPriorMinor,
        int $lifetimePriorMinor,
        string $taxYear,
    ): array {
        if ($contributionMinor < 0 || $annualPriorMinor < 0 || $lifetimePriorMinor < 0) {
            throw new InvalidArgumentException('TFSA amounts cannot be negative.');
        }

        $annualCap = $this->getAnnualContributionCap($taxYear);
        $lifetimeCap = $this->getLifetimeContributionCap($taxYear) ?? PHP_INT_MAX;
        $penaltyBps = (int) $this->config->get($taxYear, 'tfsa.over_contribution_penalty_bps', 0);

        $annualAfter = $annualPriorMinor + $contributionMinor;
        $lifetimeAfter = $lifetimePriorMinor + $contributionMinor;

        $annualExcess = max(0, $annualAfter - $annualCap);
        $lifetimeExcess = max(0, $lifetimeAfter - $lifetimeCap);

        // Per s12T(7) the penalty applies once to whichever cap is breached
        // the most. SARS doesn't double-penalise.
        $excess = max($annualExcess, $lifetimeExcess);
        $breachedCap = match (true) {
            $annualExcess === 0 && $lifetimeExcess === 0 => null,
            $annualExcess >= $lifetimeExcess => 'annual',
            default => 'lifetime',
        };

        $penalty = (int) round($excess * $penaltyBps / 10_000);

        return [
            'penalty_minor' => $penalty,
            'excess_minor' => $excess,
            'breached_cap' => $breachedCap,
            'annual_remaining_minor' => max(0, $annualCap - $annualAfter),
            'lifetime_remaining_minor' => max(0, $lifetimeCap - $lifetimeAfter),
        ];
    }

    public function getAnnualContributionCap(string $taxYear): int
    {
        return (int) $this->config->get($taxYear, 'tfsa.annual_limit_minor', 0);
    }

    public function getLifetimeContributionCap(string $taxYear): ?int
    {
        $cap = (int) $this->config->get($taxYear, 'tfsa.lifetime_limit_minor', 0);

        return $cap > 0 ? $cap : null;
    }

    public function calculateEmergencyFundTarget(
        int $essentialMonthlyExpenditureMinor,
        array $context,
        string $taxYear,
    ): array {
        if ($essentialMonthlyExpenditureMinor < 0) {
            throw new InvalidArgumentException('Essential expenditure cannot be negative.');
        }

        $stability = $context['income_stability'] ?? 'stable';
        $earners = (int) ($context['household_income_earners'] ?? 2);
        $uifEligible = (bool) ($context['uif_eligible'] ?? true);

        if (! in_array($stability, ['stable', 'volatile'], true)) {
            throw new InvalidArgumentException('income_stability must be stable or volatile.');
        }
        if ($earners < 0) {
            throw new InvalidArgumentException('household_income_earners cannot be negative.');
        }

        // Precedence: volatile > single-earner > UIF-ineligible bump > baseline.
        // A single-earner self-employed case hits single_earner first (6 mo).
        [$months, $reason] = match (true) {
            $stability === 'volatile' => [self::SINGLE_OR_VOLATILE_MONTHS, 'volatile_income'],
            $earners <= 1 => [self::SINGLE_OR_VOLATILE_MONTHS, 'single_earner'],
            ! $uifEligible => [
                self::BASELINE_MONTHS + self::UIF_INELIGIBLE_BUMP_MONTHS,
                'uif_ineligible',
            ],
            default => [self::BASELINE_MONTHS, 'dual_earner_stable'],
        };

        return [
            'target_months' => $months,
            'target_minor' => $months * $essentialMonthlyExpenditureMinor,
            'weighting_reason' => $reason,
        ];
    }
}
