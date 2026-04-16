<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Models\SavingsAccount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class LiquidityAnalyzer
{
    /**
     * Categorize accounts by liquidity level
     *
     * @return array{immediate: Collection, short_notice: Collection, fixed_term: Collection}
     */
    public function categorizeLiquidity(Collection $accounts): array
    {
        $immediate = $accounts->filter(function (SavingsAccount $account) {
            return $account->access_type === 'immediate';
        });

        $shortNotice = $accounts->filter(function (SavingsAccount $account) {
            return $account->access_type === 'notice';
        });

        $fixedTerm = $accounts->filter(function (SavingsAccount $account) {
            return $account->access_type === 'fixed';
        });

        return [
            'immediate' => $immediate,
            'short_notice' => $shortNotice,
            'fixed_term' => $fixedTerm,
        ];
    }

    /**
     * Build liquidity ladder showing when funds become available
     *
     * @return array<array{date: string, account: string, amount: float, cumulative: float, days_from_now: int}>
     */
    public function buildLiquidityLadder(Collection $accounts): array
    {
        $ladder = [];
        $now = Carbon::now();
        $cumulative = 0.0;

        // Add immediate access accounts
        foreach ($accounts->where('access_type', 'immediate') as $account) {
            $cumulative += (float) $account->current_balance;
            $ladder[] = [
                'date' => $now->format('Y-m-d'),
                'account' => $account->institution.' - '.$account->account_type,
                'amount' => round((float) $account->current_balance, 2),
                'cumulative' => round($cumulative, 2),
                'days_from_now' => 0,
                'access_type' => 'immediate',
            ];
        }

        // Add notice accounts
        foreach ($accounts->where('access_type', 'notice') as $account) {
            $availableDate = $now->copy()->addDays($account->notice_period_days ?? 0);
            $cumulative += (float) $account->current_balance;
            $ladder[] = [
                'date' => $availableDate->format('Y-m-d'),
                'account' => $account->institution.' - '.$account->account_type,
                'amount' => round((float) $account->current_balance, 2),
                'cumulative' => round($cumulative, 2),
                'days_from_now' => $account->notice_period_days ?? 0,
                'access_type' => 'notice',
            ];
        }

        // Add fixed-term accounts
        foreach ($accounts->where('access_type', 'fixed') as $account) {
            if ($account->maturity_date) {
                $maturityDate = Carbon::parse($account->maturity_date);
                $daysFromNow = max(0, $now->diffInDays($maturityDate, false));
                $cumulative += (float) $account->current_balance;
                $ladder[] = [
                    'date' => $maturityDate->format('Y-m-d'),
                    'account' => $account->institution.' - '.$account->account_type,
                    'amount' => round((float) $account->current_balance, 2),
                    'cumulative' => round($cumulative, 2),
                    'days_from_now' => (int) $daysFromNow,
                    'access_type' => 'fixed',
                ];
            }
        }

        // Sort by date
        usort($ladder, function ($a, $b) {
            return $a['days_from_now'] <=> $b['days_from_now'];
        });

        return $ladder;
    }

    /**
     * Assess liquidity risk based on profile
     *
     * Returns: Low, Medium, High
     */
    public function assessLiquidityRisk(array $liquidityProfile): string
    {
        $immediateTotal = $liquidityProfile['immediate']->sum('current_balance');
        $shortNoticeTotal = $liquidityProfile['short_notice']->sum('current_balance');
        $fixedTotal = $liquidityProfile['fixed_term']->sum('current_balance');

        $totalSavings = $immediateTotal + $shortNoticeTotal + $fixedTotal;

        if ($totalSavings == 0) {
            return 'High';
        }

        $immediatePercent = ($immediateTotal / $totalSavings) * 100;
        $liquidPercent = (($immediateTotal + $shortNoticeTotal) / $totalSavings) * 100;

        // Risk assessment criteria
        return match (true) {
            $immediatePercent >= 30 && $liquidPercent >= 60 => 'Low',
            $immediatePercent >= 20 && $liquidPercent >= 40 => 'Medium',
            default => 'High',
        };
    }

    /**
     * Get liquidity summary statistics
     *
     * @return array{total_liquid: float, total_short_notice: float, total_fixed: float, liquid_percent: float, risk_level: string}
     */
    public function getLiquiditySummary(Collection $accounts): array
    {
        $categorized = $this->categorizeLiquidity($accounts);

        $totalLiquid = $categorized['immediate']->sum('current_balance');
        $totalShortNotice = $categorized['short_notice']->sum('current_balance');
        $totalFixed = $categorized['fixed_term']->sum('current_balance');
        $totalSavings = $totalLiquid + $totalShortNotice + $totalFixed;

        $liquidPercent = $totalSavings > 0
            ? (($totalLiquid + $totalShortNotice) / $totalSavings) * 100
            : 0;

        $riskLevel = $this->assessLiquidityRisk($categorized);

        return [
            'total_liquid' => round($totalLiquid, 2),
            'total_short_notice' => round($totalShortNotice, 2),
            'total_fixed' => round($totalFixed, 2),
            'liquid_percent' => round($liquidPercent, 2),
            'risk_level' => $riskLevel,
        ];
    }
}
