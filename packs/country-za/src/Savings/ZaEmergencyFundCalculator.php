<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Savings;

use InvalidArgumentException;

/**
 * Imperative wrapper around ZaSavingsEngine's emergency-fund target calc,
 * with an adequacy assessment helper.
 *
 * The ZaSavingsEngine method is context-array shaped for contract
 * compatibility. This wrapper is the ZA-native surface for callers that
 * don't need contract uniformity (e.g. the Savings dashboard, coordinator
 * agents, CLI tools).
 */
class ZaEmergencyFundCalculator
{
    public function __construct(
        private readonly ZaSavingsEngine $engine,
    ) {
    }

    /**
     * @return array{target_months: int, target_minor: int, weighting_reason: string}
     */
    public function computeTarget(
        int $essentialMonthlyExpenditureMinor,
        string $incomeStability,
        int $householdIncomeEarners,
        bool $uifEligible,
    ): array {
        if ($essentialMonthlyExpenditureMinor < 0) {
            throw new InvalidArgumentException('Essential expenditure cannot be negative.');
        }

        return $this->engine->calculateEmergencyFundTarget(
            essentialMonthlyExpenditureMinor: $essentialMonthlyExpenditureMinor,
            context: [
                'income_stability' => $incomeStability,
                'household_income_earners' => $householdIncomeEarners,
                'uif_eligible' => $uifEligible,
            ],
            taxYear: '2026/27',
        );
    }

    /**
     * @return array{status: string, shortfall_minor: int, months_covered: float,
     *     target_months: int, target_minor: int, weighting_reason: string}
     */
    public function assess(
        int $currentBalanceMinor,
        int $essentialMonthlyExpenditureMinor,
        string $incomeStability,
        int $householdIncomeEarners,
        bool $uifEligible,
    ): array {
        $target = $this->computeTarget(
            $essentialMonthlyExpenditureMinor,
            $incomeStability,
            $householdIncomeEarners,
            $uifEligible,
        );

        $shortfall = max(0, $target['target_minor'] - $currentBalanceMinor);
        $monthsCovered = $essentialMonthlyExpenditureMinor === 0
            ? 0.0
            : round($currentBalanceMinor / $essentialMonthlyExpenditureMinor, 2);

        return [
            'status' => $shortfall === 0 ? 'adequate' : 'shortfall',
            'shortfall_minor' => $shortfall,
            'months_covered' => $monthsCovered,
            'target_months' => $target['target_months'],
            'target_minor' => $target['target_minor'],
            'weighting_reason' => $target['weighting_reason'],
        ];
    }
}
