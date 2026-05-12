<?php

declare(strict_types=1);

use Fynla\Core\Models\Goal;
use Fynla\Core\Models\GoalContribution;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\Investment\InvestmentAccount;

/**
 * G-1-b firing tests for InvestmentAccountGoalObserver.
 *
 * Observer: packs/country-gb/src/Observers/InvestmentAccountGoalObserver.php
 * Fires via TracksGoalContributions trait when an InvestmentAccount's
 * current_value increases AND a Goal is linked via
 * linked_investment_account_id.
 *
 * Side effect: creates a GoalContribution (contribution_type = "automatic"),
 * updates Goal::current_amount. Note: streak_qualifying for investment
 * contributions defaults to false (per the observer — investment auto-
 * contributions aren't streak-qualifying because investment gains are
 * market-driven, not deliberate saving).
 */

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('creates automatic GoalContribution when linked InvestmentAccount current_value increases', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 20000.00,
    ]);

    $goal = Goal::factory()->create([
        'user_id' => $this->user->id,
        'goal_type' => 'wealth_accumulation',
        'assigned_module' => 'investment',
        'linked_investment_account_id' => $account->id,
        'current_amount' => 20000.00,
        'target_amount' => 50000.00,
    ]);

    $account->update(['current_value' => 22000.00]);

    $contribution = GoalContribution::where('goal_id', $goal->id)->first();
    expect($contribution)->not->toBeNull();
    expect((float) $contribution->amount)->toBe(2000.00);
    expect($contribution->contribution_type)->toBe('automatic');
});

it('does NOT create contribution when current_value decreases', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 20000.00,
    ]);

    Goal::factory()->create([
        'user_id' => $this->user->id,
        'linked_investment_account_id' => $account->id,
        'current_amount' => 20000.00,
    ]);

    $account->update(['current_value' => 18000.00]);

    expect(GoalContribution::count())->toBe(0);
});

it('does NOT create contribution for unlinked accounts', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 10000.00,
    ]);

    // Goal exists but is not linked to this account.
    Goal::factory()->create([
        'user_id' => $this->user->id,
        'linked_investment_account_id' => null,
        'current_amount' => 10000.00,
    ]);

    $account->update(['current_value' => 12000.00]);

    expect(GoalContribution::count())->toBe(0);
});

it('uses provider + account_name in contribution note', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'provider' => 'Vanguard',
        'account_name' => 'Stocks ISA',
        'current_value' => 5000.00,
    ]);

    $goal = Goal::factory()->create([
        'user_id' => $this->user->id,
        'linked_investment_account_id' => $account->id,
        'current_amount' => 5000.00,
    ]);

    $account->update(['current_value' => 5500.00]);

    $contribution = GoalContribution::where('goal_id', $goal->id)->first();
    expect($contribution)->not->toBeNull();
    expect($contribution->notes)->toContain('Vanguard');
    expect($contribution->notes)->toContain('Stocks ISA');
});

it('respects paused goals (no contribution recorded)', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 8000.00,
    ]);

    Goal::factory()->paused()->create([
        'user_id' => $this->user->id,
        'linked_investment_account_id' => $account->id,
        'current_amount' => 8000.00,
    ]);

    $account->update(['current_value' => 9000.00]);

    expect(GoalContribution::count())->toBe(0);
});
