<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

use Fynla\Core\Models\Goal;

/**
 * Pack-mediated Goal calculation surface.
 *
 * Goal accessors (progress_percentage, days_remaining, etc.) delegate
 * to a pack-resident calculation engine because the rules (milestone
 * thresholds, on-track formulas, contribution sizing) are jurisdiction-
 * specific. Core Goal holds the data; the engine encodes the rules.
 *
 * GB pack binds this contract to its concrete GoalCalculationService.
 * SA pack would bind its own implementation once SA goals ship.
 */
interface GoalCalculationEngine
{
    public function calculateProgressPercentage(Goal $goal): float;

    public function calculateDaysRemaining(Goal $goal): int;

    public function calculateMonthsRemaining(Goal $goal): int;

    public function calculateIsOnTrack(Goal $goal): bool;

    public function calculateAmountRemaining(Goal $goal): float;

    public function calculateRequiredMonthlyContribution(Goal $goal): float;

    public function calculateCurrentMilestone(Goal $goal): ?int;

    public function calculateNextMilestone(Goal $goal): ?int;
}
