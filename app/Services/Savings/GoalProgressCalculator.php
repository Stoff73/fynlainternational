<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Models\SavingsGoal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class GoalProgressCalculator
{
    /**
     * Calculate progress for a savings goal
     *
     * @return array{months_remaining: int, shortfall: float, required_monthly_savings: float, progress_percent: float, on_track: bool}
     */
    public function calculateProgress(SavingsGoal $goal): array
    {
        $now = Carbon::now();
        $targetDate = Carbon::parse($goal->target_date);

        $monthsRemaining = max(0, $now->diffInMonths($targetDate, false));
        $shortfall = max(0, $goal->target_amount - $goal->current_saved);

        $requiredMonthlySavings = $monthsRemaining > 0
            ? $shortfall / $monthsRemaining
            : $shortfall;

        $progressPercent = $goal->target_amount > 0
            ? ($goal->current_saved / $goal->target_amount) * 100
            : 0;

        // Determine if on track (if there's an auto-transfer set up)
        $onTrack = $goal->auto_transfer_amount !== null
            && $goal->auto_transfer_amount >= $requiredMonthlySavings;

        return [
            'months_remaining' => (int) $monthsRemaining,
            'shortfall' => round($shortfall, 2),
            'required_monthly_savings' => round($requiredMonthlySavings, 2),
            'progress_percent' => round($progressPercent, 2),
            'on_track' => $onTrack,
        ];
    }

    /**
     * Project goal achievement with compound interest
     *
     * @return array{projected_final_amount: float, projected_completion_date: string, will_meet_goal: bool}
     */
    public function projectGoalAchievement(
        SavingsGoal $goal,
        float $monthlyContribution,
        float $interestRate
    ): array {
        $currentAmount = (float) $goal->current_saved;
        $targetAmount = (float) $goal->target_amount;
        $monthlyRate = $interestRate / 12;

        $now = Carbon::now();
        $targetDate = Carbon::parse($goal->target_date);
        $monthsToTarget = max(0, $now->diffInMonths($targetDate, false));

        // Calculate future value with compound interest
        // FV = PV(1+r)^n + PMT * [((1+r)^n - 1) / r]
        if ($monthlyRate > 0) {
            $compoundFactor = pow(1 + $monthlyRate, $monthsToTarget);
            $projectedAmount = $currentAmount * $compoundFactor
                + $monthlyContribution * (($compoundFactor - 1) / $monthlyRate);
        } else {
            // No interest
            $projectedAmount = $currentAmount + ($monthlyContribution * $monthsToTarget);
        }

        // Calculate when goal will be achieved
        if ($monthlyContribution > 0) {
            if ($monthlyRate > 0) {
                // Solve for n: targetAmount = currentAmount(1+r)^n + PMT * [((1+r)^n - 1) / r]
                // Simplified approximation for n
                $monthsToComplete = max(0, ($targetAmount - $currentAmount) / $monthlyContribution);
            } else {
                $monthsToComplete = ($targetAmount - $currentAmount) / $monthlyContribution;
            }

            $projectedCompletionDate = $now->copy()->addMonths((int) ceil($monthsToComplete))->format('Y-m-d');
        } else {
            $projectedCompletionDate = null;
        }

        $willMeetGoal = $projectedAmount >= $targetAmount;

        return [
            'projected_final_amount' => round($projectedAmount, 2),
            'projected_completion_date' => $projectedCompletionDate,
            'will_meet_goal' => $willMeetGoal,
        ];
    }

    /**
     * Prioritize goals by priority level and target date
     */
    public function prioritizeGoals(Collection $goals): Collection
    {
        return $goals->sortBy([
            function (SavingsGoal $goal) {
                // Sort by priority: high = 1, medium = 2, low = 3
                return match ($goal->priority) {
                    'high' => 1,
                    'medium' => 2,
                    'low' => 3,
                    default => 4,
                };
            },
            function (SavingsGoal $goal) {
                // Then by target date (earliest first)
                return $goal->target_date->timestamp;
            },
        ])->values();
    }
}
