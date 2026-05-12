<?php

declare(strict_types=1);

use App\Services\NetWorth\NetWorthService;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\SavingsAccount;

/**
 * G-1-b firing tests for NetWorthCacheObserver.
 *
 * Observer: app/Observers/NetWorthCacheObserver.php
 *
 * Fires on: created / updated / deleted of every asset/liability model that
 * registers it. Calls NetWorthService::invalidateCache($userId), plus a second
 * call for joint_owner_id when present.
 *
 * SavingsAccount is used as the representative trigger; the observer's
 * branch logic is model-agnostic (just reads user_id / joint_owner_id),
 * so per-model variations are not in scope here. The 9 registered model
 * types are smoke-asserted via EventServiceProvider introspection.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->jointOwner = User::factory()->create();

    $this->netWorthSpy = Mockery::spy(NetWorthService::class);
    app()->instance(NetWorthService::class, $this->netWorthSpy);
});

afterEach(function () {
    Mockery::close();
});

it('invalidates cache on SavingsAccount create (single-owner)', function () {
    SavingsAccount::factory()->create(['user_id' => $this->user->id]);

    $this->netWorthSpy->shouldHaveReceived('invalidateCache')
        ->with($this->user->id)
        ->once();
});

it('invalidates cache TWICE on joint SavingsAccount create (user + joint owner)', function () {
    SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'joint_owner_id' => $this->jointOwner->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.00,
    ]);

    $this->netWorthSpy->shouldHaveReceived('invalidateCache')->with($this->user->id);
    $this->netWorthSpy->shouldHaveReceived('invalidateCache')->with($this->jointOwner->id);
});

it('invalidates cache on SavingsAccount update', function () {
    $account = SavingsAccount::factory()->create(['user_id' => $this->user->id]);
    $account->update(['current_balance' => 999]);

    $this->netWorthSpy->shouldHaveReceived('invalidateCache')
        ->with($this->user->id)
        ->twice();
});

it('invalidates cache on SavingsAccount delete', function () {
    $account = SavingsAccount::factory()->create(['user_id' => $this->user->id]);
    $account->delete();

    $this->netWorthSpy->shouldHaveReceived('invalidateCache')
        ->with($this->user->id)
        ->twice();
});

it('is registered as observer for the 9 registered asset/liability model types', function () {
    $providerFile = file_get_contents(base_path('app/Providers/EventServiceProvider.php'));

    expect($providerFile)->toContain('NetWorthCacheObserver::class');

    $expectedRegistrations = [
        'Property', 'Mortgage', 'SavingsAccount', 'InvestmentAccount', 'DCPension',
        'BusinessInterest', 'Chattel', 'Asset', 'Liability',
    ];
    foreach ($expectedRegistrations as $modelShortName) {
        expect($providerFile)->toMatch(
            "/{$modelShortName}::class\s*=>\s*\[[^\]]*NetWorthCacheObserver/"
        );
    }
});
