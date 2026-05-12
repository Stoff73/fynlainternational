<?php

declare(strict_types=1);

use Fynla\Core\Models\Goal;
use Fynla\Core\Models\GoalContribution;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\SavingsAccount;

/**
 * G-1-b scaffold for SavingsAccountGoalObserver firing tests.
 *
 * Observer: packs/country-gb/src/Observers/SavingsAccountGoalObserver.php
 * Fires via TracksGoalContributions trait when a SavingsAccount's
 * current_balance increases AND a Goal is linked via
 * goal_savings_account pivot table (with fallback to legacy
 * linked_savings_account_id FK).
 *
 * Side effect: creates a GoalContribution (contribution_type = "automatic"),
 * updates Goal::current_amount, qualifies the auto-contribution streak.
 *
 * Note: tests/Unit/Observers/GoalObserversTest.php already covers the
 * core happy paths under the post-relocation namespaces. G-1-b should
 * reuse / extend those rather than duplicate. This scaffold exists for
 * Feature-level coverage parity.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('creates automatic GoalContribution when linked SavingsAccount current_balance increases')
    ->todo('G-1-b: confirms Unit test coverage holds at Feature level post-relocation');

it('uses institution + account_name in contribution note')
    ->todo('G-1-b: assert GoalContribution::note matches "Auto-tracked from {institution} ({account_name})"');

it('qualifies the contribution as auto-contribution-streak-qualifying')
    ->todo('G-1-b: assert streak increment / qualifies-for-streak flag is set');

it('resolves the linked Goal via the goal_savings_account pivot when present')
    ->todo('G-1-b: assert pivot path overrides legacy linked_savings_account_id FK');
