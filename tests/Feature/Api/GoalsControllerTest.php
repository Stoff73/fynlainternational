<?php

declare(strict_types=1);

use Fynla\Core\Models\Goal;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Http\Resources\GoalResource;
use Fynla\Packs\Gb\Models\SavingsAccount;

describe('GoalsController', function () {
    it('returns goals with a linked savings account', function () {
        // Regression: GoalResource referenced the pre-relocation
        // App\Http\Resources\SavingsAccountResource, so any goal with a
        // linked savings account 500'd the whole /api/goals index.
        $user = User::factory()->create();
        $account = SavingsAccount::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'linked_savings_account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/goals');

        $response->assertOk()
            ->assertJsonPath('data.goals.0.id', $goal->id)
            ->assertJsonPath('data.goals.0.linked_savings_account.id', $account->id);
    });

    it('renders a goal joint owner via the relocated UserResource', function () {
        // Regression: GoalResource referenced UserResource unqualified after
        // it moved to Fynla\Core\Http\Resources — fatal when jointOwner loaded.
        $user = User::factory()->create();
        $spouse = User::factory()->create();
        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'joint_owner_id' => $spouse->id,
            'ownership_type' => 'joint',
        ]);

        $data = GoalResource::make($goal->load('jointOwner'))->resolve();

        expect($data['joint_owner']['id'])->toBe($spouse->id);
    });
});
