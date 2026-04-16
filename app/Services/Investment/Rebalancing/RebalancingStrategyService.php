<?php

declare(strict_types=1);

namespace App\Services\Investment\Rebalancing;

/**
 * Rebalancing Strategy Service
 * Implements different portfolio rebalancing strategies
 *
 * Strategies:
 * 1. Calendar-based: Rebalance at fixed intervals (quarterly, semi-annual, annual)
 * 2. Threshold-based: Rebalance when allocation drifts by X%
 * 3. Tolerance band: Rebalance when outside tolerance bands
 * 4. Opportunistic: Rebalance with new contributions/withdrawals
 * 5. Tax-aware: Minimize CGT impact while rebalancing
 */
class RebalancingStrategyService
{
    /**
     * Evaluate if portfolio should be rebalanced using threshold strategy
     *
     * @param  array  $currentAllocation  Current allocation percentages
     * @param  array  $targetAllocation  Target allocation percentages
     * @param  float  $thresholdPercent  Drift threshold (e.g., 5.0 for 5%)
     * @return array Evaluation result
     */
    public function evaluateThresholdStrategy(
        array $currentAllocation,
        array $targetAllocation,
        float $thresholdPercent = 5.0
    ): array {
        $drifts = [];
        $maxDrift = 0.0;
        $needsRebalancing = false;
        $assetsToRebalance = [];

        foreach ($targetAllocation as $assetClass => $targetPercent) {
            $currentPercent = $currentAllocation[$assetClass] ?? 0.0;
            $drift = abs($currentPercent - $targetPercent);

            $drifts[$assetClass] = [
                'current' => $currentPercent,
                'target' => $targetPercent,
                'drift' => $drift,
                'drift_percent' => ($drift / max(0.01, $targetPercent)) * 100,
            ];

            if ($drift > $thresholdPercent) {
                $needsRebalancing = true;
                $assetsToRebalance[] = $assetClass;
            }

            $maxDrift = max($maxDrift, $drift);
        }

        return [
            'strategy' => 'threshold',
            'needs_rebalancing' => $needsRebalancing,
            'threshold_percent' => $thresholdPercent,
            'max_drift' => $maxDrift,
            'drifts' => $drifts,
            'assets_to_rebalance' => $assetsToRebalance,
            'recommendation' => $this->generateThresholdRecommendation(
                $needsRebalancing,
                $maxDrift,
                $thresholdPercent
            ),
        ];
    }

    /**
     * Evaluate tolerance band strategy
     *
     * @param  array  $currentAllocation  Current allocation
     * @param  array  $targetAllocation  Target allocation
     * @param  float  $toleranceBandPercent  Tolerance band width (e.g., 5.0 for ±5%)
     * @return array Evaluation result
     */
    public function evaluateToleranceBandStrategy(
        array $currentAllocation,
        array $targetAllocation,
        float $toleranceBandPercent = 5.0
    ): array {
        $bands = [];
        $breaches = [];
        $needsRebalancing = false;

        foreach ($targetAllocation as $assetClass => $targetPercent) {
            $currentPercent = $currentAllocation[$assetClass] ?? 0.0;

            $upperBand = $targetPercent + $toleranceBandPercent;
            $lowerBand = max(0.0, $targetPercent - $toleranceBandPercent);

            $isWithinBand = ($currentPercent >= $lowerBand && $currentPercent <= $upperBand);
            $breachAmount = 0.0;

            if ($currentPercent > $upperBand) {
                $breachAmount = $currentPercent - $upperBand;
                $needsRebalancing = true;
                $breaches[] = [
                    'asset_class' => $assetClass,
                    'type' => 'over',
                    'breach' => $breachAmount,
                ];
            } elseif ($currentPercent < $lowerBand) {
                $breachAmount = $lowerBand - $currentPercent;
                $needsRebalancing = true;
                $breaches[] = [
                    'asset_class' => $assetClass,
                    'type' => 'under',
                    'breach' => $breachAmount,
                ];
            }

            $bands[$assetClass] = [
                'target' => $targetPercent,
                'current' => $currentPercent,
                'lower_band' => $lowerBand,
                'upper_band' => $upperBand,
                'within_band' => $isWithinBand,
                'breach_amount' => $breachAmount,
            ];
        }

        return [
            'strategy' => 'tolerance_band',
            'needs_rebalancing' => $needsRebalancing,
            'tolerance_band_percent' => $toleranceBandPercent,
            'bands' => $bands,
            'breaches' => $breaches,
            'breach_count' => count($breaches),
            'recommendation' => $this->generateToleranceBandRecommendation(
                $needsRebalancing,
                count($breaches)
            ),
        ];
    }

    /**
     * Evaluate calendar-based rebalancing strategy
     *
     * @param  string  $lastRebalanceDate  Last rebalance date (YYYY-MM-DD)
     * @param  string  $frequency  Rebalancing frequency (quarterly, semi_annual, annual, biennial)
     * @return array Evaluation result
     */
    public function evaluateCalendarStrategy(
        string $lastRebalanceDate,
        string $frequency = 'annual'
    ): array {
        $lastRebalance = new \DateTime($lastRebalanceDate);
        $today = new \DateTime;
        $daysSince = $today->diff($lastRebalance)->days;

        $frequencyDays = match ($frequency) {
            'quarterly' => 90,
            'semi_annual' => 182,
            'annual' => 365,
            'biennial' => 730,
            default => 365,
        };

        $needsRebalancing = $daysSince >= $frequencyDays;
        $daysUntilNext = max(0, $frequencyDays - $daysSince);
        $nextRebalanceDate = (clone $lastRebalance)->modify("+{$frequencyDays} days");

        return [
            'strategy' => 'calendar',
            'needs_rebalancing' => $needsRebalancing,
            'frequency' => $frequency,
            'last_rebalance_date' => $lastRebalanceDate,
            'days_since_rebalance' => $daysSince,
            'frequency_days' => $frequencyDays,
            'days_until_next' => $daysUntilNext,
            'next_rebalance_date' => $nextRebalanceDate->format('Y-m-d'),
            'progress_percent' => min(100, ($daysSince / $frequencyDays) * 100),
            'recommendation' => $this->generateCalendarRecommendation(
                $needsRebalancing,
                $daysUntilNext,
                $nextRebalanceDate->format('Y-m-d')
            ),
        ];
    }

    /**
     * Evaluate opportunistic rebalancing with new cash flow
     *
     * @param  array  $currentAllocation  Current allocation
     * @param  array  $targetAllocation  Target allocation
     * @param  float  $newCashFlow  New contribution or withdrawal amount
     * @param  float  $portfolioValue  Current portfolio value
     * @return array Evaluation result
     */
    public function evaluateOpportunisticStrategy(
        array $currentAllocation,
        array $targetAllocation,
        float $newCashFlow,
        float $portfolioValue
    ): array {
        if ($portfolioValue <= 0) {
            return [
                'strategy' => 'opportunistic',
                'feasible' => false,
                'message' => 'Invalid portfolio value',
            ];
        }

        $cashFlowPercent = ($newCashFlow / $portfolioValue) * 100;
        $isContribution = $newCashFlow > 0;

        // Calculate how new cash should be allocated
        $newPortfolioValue = $portfolioValue + $newCashFlow;
        $recommendations = [];

        foreach ($targetAllocation as $assetClass => $targetPercent) {
            $currentPercent = $currentAllocation[$assetClass] ?? 0.0;
            $currentValue = ($currentPercent / 100) * $portfolioValue;

            // Target value after cash flow
            $targetValue = ($targetPercent / 100) * $newPortfolioValue;

            // Amount to add/remove to reach target
            $amountNeeded = $targetValue - $currentValue;

            $recommendations[$assetClass] = [
                'current_value' => $currentValue,
                'current_percent' => $currentPercent,
                'target_percent' => $targetPercent,
                'target_value' => $targetValue,
                'amount_needed' => $amountNeeded,
                'action' => $amountNeeded > 0 ? 'buy' : 'sell',
            ];
        }

        // Check if opportunistic rebalancing is worthwhile
        $significantCashFlow = abs($cashFlowPercent) >= 2.0; // 2% threshold

        return [
            'strategy' => 'opportunistic',
            'feasible' => $significantCashFlow,
            'cash_flow' => $newCashFlow,
            'cash_flow_percent' => $cashFlowPercent,
            'is_contribution' => $isContribution,
            'portfolio_value' => $portfolioValue,
            'new_portfolio_value' => $newPortfolioValue,
            'recommendations' => $recommendations,
            'recommendation' => $this->generateOpportunisticRecommendation(
                $significantCashFlow,
                $isContribution,
                $cashFlowPercent
            ),
        ];
    }

    /**
     * Compare multiple rebalancing strategies
     *
     * @param  array  $currentAllocation  Current allocation
     * @param  array  $targetAllocation  Target allocation
     * @param  array  $options  Strategy options
     * @return array Comparison of all strategies
     */
    public function compareStrategies(
        array $currentAllocation,
        array $targetAllocation,
        array $options = []
    ): array {
        $thresholdPercent = $options['threshold_percent'] ?? 5.0;
        $toleranceBandPercent = $options['tolerance_band_percent'] ?? 5.0;
        $lastRebalanceDate = $options['last_rebalance_date'] ?? date('Y-m-d', strtotime('-6 months'));
        $frequency = $options['frequency'] ?? 'annual';

        $strategies = [
            'threshold' => $this->evaluateThresholdStrategy(
                $currentAllocation,
                $targetAllocation,
                $thresholdPercent
            ),
            'tolerance_band' => $this->evaluateToleranceBandStrategy(
                $currentAllocation,
                $targetAllocation,
                $toleranceBandPercent
            ),
            'calendar' => $this->evaluateCalendarStrategy(
                $lastRebalanceDate,
                $frequency
            ),
        ];

        // Determine overall recommendation
        $rebalanceCount = 0;
        foreach ($strategies as $strategy) {
            if ($strategy['needs_rebalancing'] ?? false) {
                $rebalanceCount++;
            }
        }

        $overallRecommendation = match (true) {
            $rebalanceCount >= 2 => 'Strong recommendation to rebalance - multiple strategies agree',
            $rebalanceCount === 1 => 'Moderate recommendation to rebalance - one strategy triggered',
            default => 'No rebalancing needed - portfolio is within acceptable drift',
        };

        return [
            'strategies' => $strategies,
            'rebalance_signals' => $rebalanceCount,
            'overall_recommendation' => $overallRecommendation,
            'consensus' => $rebalanceCount >= 2,
        ];
    }

    /**
     * Recommend optimal rebalancing frequency based on portfolio characteristics
     *
     * @param  float  $portfolioValue  Portfolio value
     * @param  int  $riskLevel  Risk level (1-5)
     * @param  float  $expectedVolatility  Expected portfolio volatility
     * @param  bool  $isTaxable  Whether account is taxable
     * @return array Frequency recommendation
     */
    public function recommendRebalancingFrequency(
        float $portfolioValue,
        int $riskLevel,
        float $expectedVolatility,
        bool $isTaxable = true
    ): array {
        // Higher volatility = more frequent monitoring needed
        // Lower portfolio value = less frequent (transaction costs)
        // Taxable accounts = less frequent (CGT costs)
        // Higher risk = can tolerate more drift, less frequent

        $score = 0;

        // Portfolio size factor
        if ($portfolioValue < 10000) {
            $score += 1; // Annual
        } elseif ($portfolioValue < 50000) {
            $score += 2; // Semi-annual
        } elseif ($portfolioValue < 100000) {
            $score += 3; // Quarterly to semi-annual
        } else {
            $score += 4; // Quarterly
        }

        // Volatility factor
        if ($expectedVolatility < 5) {
            $score += 1; // Low volatility
        } elseif ($expectedVolatility < 10) {
            $score += 2;
        } elseif ($expectedVolatility < 15) {
            $score += 3;
        } else {
            $score += 4; // High volatility
        }

        // Risk level factor (inverse relationship)
        $score += (6 - $riskLevel);

        // Tax factor
        if ($isTaxable) {
            $score -= 2; // Less frequent for taxable
        }

        // Determine frequency
        $recommendation = match (true) {
            $score <= 4 => [
                'frequency' => 'annual',
                'days' => 365,
                'rationale' => 'Annual rebalancing recommended due to low portfolio value or high CGT impact',
            ],
            $score <= 6 => [
                'frequency' => 'semi_annual',
                'days' => 182,
                'rationale' => 'Semi-annual rebalancing recommended for moderate portfolio',
            ],
            $score <= 8 => [
                'frequency' => 'quarterly',
                'days' => 90,
                'rationale' => 'Quarterly rebalancing recommended for larger, volatile portfolio',
            ],
            default => [
                'frequency' => 'quarterly',
                'days' => 90,
                'rationale' => 'Quarterly rebalancing recommended with monthly monitoring',
                'additional_note' => 'Consider monthly monitoring given high volatility',
            ],
        };

        return [
            'recommended_frequency' => $recommendation['frequency'],
            'days_between_rebalancing' => $recommendation['days'],
            'rationale' => $recommendation['rationale'],
            'additional_note' => $recommendation['additional_note'] ?? null,
            'factors' => [
                'portfolio_value' => $portfolioValue,
                'risk_level' => $riskLevel,
                'expected_volatility' => $expectedVolatility,
                'is_taxable' => $isTaxable,
            ],
            'threshold_suggestion' => $this->recommendThreshold($riskLevel, $expectedVolatility),
        ];
    }

    /**
     * Recommend drift threshold based on portfolio characteristics
     *
     * @param  int  $riskLevel  Risk level (1-5)
     * @param  float  $expectedVolatility  Expected volatility
     * @return array Threshold recommendation
     */
    private function recommendThreshold(int $riskLevel, float $expectedVolatility): array
    {
        // Conservative portfolios: tighter threshold (5%)
        // Aggressive portfolios: wider threshold (15-20%)
        // Higher volatility: wider threshold to avoid over-trading

        $baseThreshold = match ($riskLevel) {
            1 => 5.0,  // Very Conservative
            2 => 7.0,  // Conservative
            3 => 10.0, // Moderate
            4 => 15.0, // Growth
            5 => 20.0, // Aggressive
            default => 10.0,
        };

        // Adjust for volatility
        if ($expectedVolatility > 15) {
            $baseThreshold += 5.0;
        } elseif ($expectedVolatility > 10) {
            $baseThreshold += 2.5;
        }

        return [
            'threshold_percent' => $baseThreshold,
            'rationale' => sprintf(
                'Based on risk level %d and %.1f%% volatility',
                $riskLevel,
                $expectedVolatility
            ),
        ];
    }

    /**
     * Generate threshold strategy recommendation
     */
    private function generateThresholdRecommendation(
        bool $needsRebalancing,
        float $maxDrift,
        float $threshold
    ): string {
        if ($needsRebalancing) {
            return sprintf(
                'Rebalancing recommended: Maximum drift of %.1f%% exceeds threshold of %.1f%%',
                $maxDrift,
                $threshold
            );
        }

        return sprintf(
            'No rebalancing needed: Maximum drift of %.1f%% is within threshold of %.1f%%',
            $maxDrift,
            $threshold
        );
    }

    /**
     * Generate tolerance band recommendation
     */
    private function generateToleranceBandRecommendation(
        bool $needsRebalancing,
        int $breachCount
    ): string {
        if ($needsRebalancing) {
            return sprintf(
                'Rebalancing recommended: %d asset class%s outside tolerance bands',
                $breachCount,
                $breachCount > 1 ? 'es are' : ' is'
            );
        }

        return 'All asset classes are within tolerance bands - no rebalancing needed';
    }

    /**
     * Generate calendar recommendation
     */
    private function generateCalendarRecommendation(
        bool $needsRebalancing,
        int $daysUntilNext,
        string $nextDate
    ): string {
        if ($needsRebalancing) {
            return 'Rebalancing recommended: Scheduled rebalancing date has been reached';
        }

        return sprintf(
            'Next scheduled rebalancing in %d days on %s',
            $daysUntilNext,
            $nextDate
        );
    }

    /**
     * Generate opportunistic recommendation
     */
    private function generateOpportunisticRecommendation(
        bool $feasible,
        bool $isContribution,
        float $cashFlowPercent
    ): string {
        if (! $feasible) {
            return sprintf(
                'Cash flow of %.1f%% is too small for opportunistic rebalancing (minimum 2%% recommended)',
                abs($cashFlowPercent)
            );
        }

        $action = $isContribution ? 'contribution' : 'withdrawal';

        return sprintf(
            'Opportunistic rebalancing recommended: Use %s of %.1f%% to move closer to target allocation',
            $action,
            abs($cashFlowPercent)
        );
    }
}
