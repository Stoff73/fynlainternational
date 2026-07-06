<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Database\Seeders\TaxConfigurationSeeder;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * G-4-b slice 4, finding S4-H1 — cross-user savings-deposit leak.
 *
 * StoreGoalRequest/UpdateGoalRequest validated linked_savings_account_id only
 * as exists:savings_accounts,id (unscoped). An attacker could link their goal
 * to a victim's account; the TracksGoalContributions observer then wrote the
 * victim's deposit deltas onto the attacker's goal (resolveLinkedGoals filters
 * only by account id, never owner). Fix: the FK must be scoped to an account
 * the caller owns or jointly owns.
 */
it('rejects a goal linked to a savings account the caller does not own', function () {
    $attacker = User::factory()->create();
    $victim = User::factory()->create();

    $victimAccount = SavingsAccount::factory()->create([
        'user_id' => $victim->id,
        'current_balance' => 1000,
    ]);

    Sanctum::actingAs($attacker);

    $this->postJson('/api/gb/goals', [
        'goal_name' => 'Steal deposits',
        'goal_type' => 'wealth_accumulation',
        'target_amount' => 10000,
        'target_date' => now()->addYear()->toDateString(),
        'linked_savings_account_id' => $victimAccount->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['linked_savings_account_id']);
});

it('accepts a goal linked to an account the caller owns', function () {
    $user = User::factory()->create();
    $ownAccount = SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 1000,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/gb/goals', [
        'goal_name' => 'My savings goal',
        'goal_type' => 'wealth_accumulation',
        'target_amount' => 10000,
        'target_date' => now()->addYear()->toDateString(),
        'linked_savings_account_id' => $ownAccount->id,
    ])->assertStatus(201);
});

it('accepts a goal linked to an account the caller jointly owns', function () {
    $user = User::factory()->create();
    $spouse = User::factory()->create();
    $jointAccount = SavingsAccount::factory()->create([
        'user_id' => $spouse->id,
        'joint_owner_id' => $user->id,
        'ownership_type' => 'joint',
        'current_balance' => 1000,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/gb/goals', [
        'goal_name' => 'Joint goal',
        'goal_type' => 'wealth_accumulation',
        'target_amount' => 10000,
        'target_date' => now()->addYear()->toDateString(),
        'linked_savings_account_id' => $jointAccount->id,
    ])->assertStatus(201);
});
