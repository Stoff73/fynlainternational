<?php

declare(strict_types=1);

namespace App\Services\Investment\Rebalancing;

use App\Models\Investment\InvestmentAccount;
use Illuminate\Support\Collection;

/**
 * Calculate rebalancing trades to move from current to target allocation
 * Determines specific buy/sell actions with £ amounts
 */
class RebalancingCalculator
{
    /**
     * Calculate rebalancing actions needed to reach target allocation
     *
     * @param  Collection  $holdings  Current holdings
     * @param  array  $targetWeights  Target weights (same order as holdings)
     * @param  array  $options  Options (min_trade_size, account_cash)
     * @return array Rebalancing analysis with actions
     */
    public function calculateRebalancing(
        Collection $holdings,
        array $targetWeights,
        array $options = []
    ): array {
        $minTradeSize = $options['min_trade_size'] ?? 100; // Minimum £100 trade
        $accountCash = $options['account_cash'] ?? 0;
        $accountId = $options['account_id'] ?? null;

        // Calculate total portfolio value
        $totalValue = $holdings->sum('current_value') + $accountCash;

        if ($totalValue <= 0) {
            return [
                'success' => false,
                'message' => 'Portfolio value must be greater than zero',
            ];
        }

        // Validate weights
        if (count($targetWeights) !== $holdings->count()) {
            return [
                'success' => false,
                'message' => 'Number of target weights must match number of holdings',
            ];
        }

        $weightSum = array_sum($targetWeights);
        if (abs($weightSum - 1.0) > 0.01) {
            return [
                'success' => false,
                'message' => 'Target weights must sum to 1.0 (100%)',
            ];
        }

        // Calculate current weights
        $currentWeights = [];
        $currentAllocations = [];
        foreach ($holdings as $index => $holding) {
            $currentWeights[$index] = $holding->current_value / $totalValue;
            $currentAllocations[$index] = [
                'holding_id' => $holding->id,
                'security_name' => $holding->security_name ?? $holding->ticker,
                'ticker' => $holding->ticker,
                'current_value' => $holding->current_value,
                'current_weight' => $currentWeights[$index],
                'current_price' => $holding->current_price,
                'quantity' => $holding->quantity,
            ];
        }

        // Calculate target allocations
        $targetAllocations = [];
        $actions = [];

        foreach ($holdings as $index => $holding) {
            $targetValue = $totalValue * $targetWeights[$index];
            $currentValue = $holding->current_value;
            $difference = $targetValue - $currentValue;
            $differencePercent = ($currentValue > 0) ? ($difference / $currentValue) * 100 : 0;

            $targetAllocations[$index] = [
                'holding_id' => $holding->id,
                'security_name' => $holding->security_name ?? $holding->ticker,
                'ticker' => $holding->ticker,
                'target_value' => $targetValue,
                'target_weight' => $targetWeights[$index],
                'difference' => $difference,
                'difference_percent' => $differencePercent,
            ];

            // Generate action if difference exceeds minimum trade size
            if (abs($difference) >= $minTradeSize) {
                $action = $this->generateAction(
                    $holding,
                    $difference,
                    $targetValue,
                    $targetWeights[$index],
                    $accountId
                );
                $actions[] = $action;
            }
        }

        // Calculate metrics
        $metrics = $this->calculateRebalancingMetrics(
            $currentWeights,
            $targetWeights,
            $holdings,
            $actions
        );

        // Sort actions: sells first, then buys
        usort($actions, function ($a, $b) {
            if ($a['action_type'] === 'sell' && $b['action_type'] === 'buy') {
                return -1;
            }
            if ($a['action_type'] === 'buy' && $b['action_type'] === 'sell') {
                return 1;
            }

            return abs($b['trade_value']) <=> abs($a['trade_value']);
        });

        return [
            'success' => true,
            'total_portfolio_value' => $totalValue,
            'account_cash' => $accountCash,
            'current_allocations' => array_values($currentAllocations),
            'target_allocations' => array_values($targetAllocations),
            'actions' => $actions,
            'metrics' => $metrics,
            'summary' => $this->generateSummary($actions, $metrics),
        ];
    }

    /**
     * Generate a rebalancing action for a holding
     *
     * @param  object  $holding  Holding model
     * @param  float  $difference  £ difference (positive = buy, negative = sell)
     * @param  float  $targetValue  Target £ value
     * @param  float  $targetWeight  Target weight
     * @param  int|null  $accountId  Account ID
     * @return array Action details
     */
    private function generateAction(
        $holding,
        float $difference,
        float $targetValue,
        float $targetWeight,
        ?int $accountId
    ): array {
        $actionType = $difference > 0 ? 'buy' : 'sell';
        $tradeValue = abs($difference);
        $currentPrice = $holding->current_price ?? 0;

        // Calculate shares to trade
        $sharesToTrade = 0;
        if ($currentPrice > 0) {
            $sharesToTrade = abs($difference) / $currentPrice;
            // Round down for sells, up for buys (to avoid over-selling)
            $sharesToTrade = $actionType === 'sell'
                ? floor($sharesToTrade * 100) / 100
                : ceil($sharesToTrade * 100) / 100;
        }

        // Calculate actual trade value after rounding
        $actualTradeValue = $sharesToTrade * $currentPrice;

        return [
            'holding_id' => $holding->id,
            'account_id' => $accountId,
            'security_name' => $holding->security_name ?? $holding->ticker,
            'ticker' => $holding->ticker ?? 'N/A',
            'isin' => $holding->isin,
            'action_type' => $actionType,
            'trade_value' => $actualTradeValue,
            'shares_to_trade' => $sharesToTrade,
            'current_price' => $currentPrice,
            'current_holding' => $holding->quantity ?? 0,
            'target_value' => $targetValue,
            'target_weight' => $targetWeight,
            'priority' => $this->calculateActionPriority($difference, $targetWeight),
            'rationale' => $this->generateRationale($actionType, $difference, $targetWeight),
        ];
    }

    /**
     * Calculate priority for an action (1 = highest)
     *
     * @param  float  $difference  £ difference
     * @param  float  $targetWeight  Target weight
     * @return int Priority (1-5)
     */
    private function calculateActionPriority(float $difference, float $targetWeight): int
    {
        // Higher priority for larger differences and higher target weights
        $diffMagnitude = abs($difference);

        if ($diffMagnitude > 10000) {
            return 1; // High priority
        } elseif ($diffMagnitude > 5000) {
            return 2;
        } elseif ($diffMagnitude > 2000) {
            return 3;
        } elseif ($diffMagnitude > 1000) {
            return 4;
        }

        return 5; // Low priority
    }

    /**
     * Generate rationale for an action
     *
     * @param  string  $actionType  'buy' or 'sell'
     * @param  float  $difference  £ difference
     * @param  float  $targetWeight  Target weight
     * @return string Rationale text
     */
    private function generateRationale(string $actionType, float $difference, float $targetWeight): string
    {
        $percentDiff = ($targetWeight * 100);
        $verb = $actionType === 'buy' ? 'increase' : 'decrease';

        return sprintf(
            '%s allocation to reach target weight of %.1f%%',
            ucfirst($verb),
            $percentDiff
        );
    }

    /**
     * Calculate rebalancing metrics
     *
     * @param  array  $currentWeights  Current weights
     * @param  array  $targetWeights  Target weights
     * @param  Collection  $holdings  Holdings
     * @param  array  $actions  Rebalancing actions
     * @return array Metrics
     */
    private function calculateRebalancingMetrics(
        array $currentWeights,
        array $targetWeights,
        Collection $holdings,
        array $actions
    ): array {
        // Calculate tracking error (how far from target)
        $trackingError = 0;
        for ($i = 0; $i < count($currentWeights); $i++) {
            $trackingError += pow($currentWeights[$i] - $targetWeights[$i], 2);
        }
        $trackingError = sqrt($trackingError);

        // Calculate total trades needed
        $totalBuys = 0;
        $totalSells = 0;
        $numBuyActions = 0;
        $numSellActions = 0;

        foreach ($actions as $action) {
            if ($action['action_type'] === 'buy') {
                $totalBuys += $action['trade_value'];
                $numBuyActions++;
            } else {
                $totalSells += $action['trade_value'];
                $numSellActions++;
            }
        }

        $totalTurnover = $totalBuys + $totalSells;

        return [
            'tracking_error' => round($trackingError, 4),
            'total_turnover' => round($totalTurnover, 2),
            'total_buys' => round($totalBuys, 2),
            'total_sells' => round($totalSells, 2),
            'num_buy_actions' => $numBuyActions,
            'num_sell_actions' => $numSellActions,
            'total_actions' => count($actions),
            'needs_rebalancing' => $trackingError > 0.05, // More than 5% tracking error
        ];
    }

    /**
     * Generate summary text
     *
     * @param  array  $actions  Rebalancing actions
     * @param  array  $metrics  Rebalancing metrics
     * @return string Summary text
     */
    private function generateSummary(array $actions, array $metrics): string
    {
        if (empty($actions)) {
            return 'Portfolio is already well-balanced. No rebalancing needed at this time.';
        }

        $parts = [];

        if ($metrics['needs_rebalancing']) {
            $parts[] = sprintf(
                'Portfolio requires rebalancing: %d action%s needed',
                $metrics['total_actions'],
                $metrics['total_actions'] === 1 ? '' : 's'
            );
        }

        if ($metrics['num_sell_actions'] > 0) {
            $parts[] = sprintf(
                'Sell £%s across %d holding%s',
                number_format($metrics['total_sells'], 2),
                $metrics['num_sell_actions'],
                $metrics['num_sell_actions'] === 1 ? '' : 's'
            );
        }

        if ($metrics['num_buy_actions'] > 0) {
            $parts[] = sprintf(
                'Buy £%s across %d holding%s',
                number_format($metrics['total_buys'], 2),
                $metrics['num_buy_actions'],
                $metrics['num_buy_actions'] === 1 ? '' : 's'
            );
        }

        $parts[] = sprintf(
            'Total turnover: £%s (%.1f%% of portfolio)',
            number_format($metrics['total_turnover'], 2),
            0 // Will calculate percentage in controller
        );

        return implode('. ', $parts).'.';
    }

    /**
     * Calculate rebalancing from optimal portfolio weights
     * Convenience method that integrates with portfolio optimization
     *
     * @param  int  $userId  User ID
     * @param  array  $optimizationResult  Result from MarkowitzOptimizer
     * @param  array  $options  Additional options
     * @return array Rebalancing analysis
     */
    public function calculateFromOptimization(
        int $userId,
        array $optimizationResult,
        array $options = []
    ): array {
        // Get user's holdings
        $accounts = InvestmentAccount::where('user_id', $userId)
            ->with('holdings')
            ->get();

        if ($accounts->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No investment accounts found',
            ];
        }

        $holdings = $accounts->flatMap->holdings;

        if ($holdings->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No holdings found',
            ];
        }

        // Extract target weights from optimization result
        $targetWeights = $optimizationResult['weights'] ?? [];

        if (empty($targetWeights)) {
            return [
                'success' => false,
                'message' => 'No target weights provided in optimization result',
            ];
        }

        // Calculate total cash available in accounts
        $totalCash = $accounts->sum(function ($account) {
            // Assume cash holdings have asset_type = 'cash'
            return $account->holdings()
                ->where('asset_type', 'cash')
                ->sum('current_value');
        });

        $options['account_cash'] = $totalCash;

        return $this->calculateRebalancing($holdings, $targetWeights, $options);
    }
}
