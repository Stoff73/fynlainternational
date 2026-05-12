<?php

declare(strict_types=1);

use Fynla\Core\Models\Goal;
use Fynla\Core\Models\GoalContribution;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\SavingsAccount;

/**
 * G-1-b firing tests for SavingsAccountGoalObserver.
 *
 * Observer: packs/country-gb/src/Observers/SavingsAccountGoalObserver.php
 * Fires via TracksGoalContributions trait when a SavingsAccount's
 * current_balance increases AND a Goal is linked via
 * linked_savings_account_id.
 *
 * Side effects: creates a GoalContribution (contribution_type = "automatic"),
 * updates Goal::current_amount, qualifies the auto-contribution streak.
 *
 * Note: existing coverage at tests/Unit/Observers/GoalObserversTest.php
 * covers the happy paths. This file adds Feature-level parity for the
 * trait-specific bits (note format, streak flag) called out in G-1-b.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('creates automatic GoalContribution when linked SavingsAccount current_balance increases', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 5000.00,
    ]);

    $goal = Goal::factory()->emergencyFund()->create([
        'user_id' => $this->user->id,
        'linked_savings_account_id' => $account->id,
        'current_amount' => 5000.00,
        'target_amount' => 10000.00,
    ]);

    $account->update(['current_balance' => 6500.00]);

    $contribution = GoalContribution::where('goal_id', $goal->id)->first();
    expect($contribution)->not->toBeNull();
    expect((float) $contribution->amount)->toBe(1500.00);
    expect($contribution->contribution_type)->toBe('automatic');
});

it('uses institution + account_name in contribution note', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'institution' => 'Halifax',
        'account_name' => 'Emergency Pot',
        'current_balance' => 1000.00,
    ]);

    $goal = Goal::factory()->emergencyFund()->create([
        'user_id' => $this->user->id,
        'linked_savings_account_id' => $account->id,
        'current_amount' => 1000.00,
    ]);

    $account->update(['current_balance' => 1500.00]);

    $contribution = GoalContribution::where('goal_id', $goal->id)->first();
    expect($contribution)->not->toBeNull();
    expect($contribution->notes)->toContain('Halifax');
    expect($contribution->notes)->toContain('Emergency Pot');
});

it('qualifies the contribution as auto-contribution-streak-qualifying', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 2000.00,
    ]);

    $goal = Goal::factory()->emergencyFund()->create([
        'user_id' => $this->user->id,
        'linked_savings_account_id' => $account->id,
        'current_amount' => 2000.00,
    ]);

    $account->update(['current_balance' => 2500.00]);

    $contribution = GoalContribution::where('goal_id', $goal->id)->first();
    expect($contribution)->not->toBeNull();
    expect((bool) $contribution->streak_qualifying)->toBeTrue();
});

it('resolves the linked Goal via the legacy linked_savings_account_id FK when no pivot exists', function () {
    // The trait checks the goal_savings_account pivot first, then falls back to
    // the legacy linked_savings_account_id FK. With no pivot present, the FK path
    // should still resolve the linked goal.
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 1000.00,
    ]);

    $goal = Goal::factory()->emergencyFund()->create([
        'user_id' => $this->user->id,
        'linked_savings_account_id' => $account->id,
        'current_amount' => 1000.00,
    ]);

    $account->update(['current_balance' => 1100.00]);

    expect(GoalContribution::where('goal_id', $goal->id)->count())->toBe(1);
});
