<?php

declare(strict_types=1);

use App\Services\NetWorth\NetWorthService;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\SavingsAccount;

/**
 * G-1-b scaffold for NetWorthCacheObserver firing tests.
 *
 * Observer: app/Observers/NetWorthCacheObserver.php
 * Fires on: created / updated / deleted of every asset/liability model that
 *           registers it. Calls NetWorthService::invalidateCache($userId)
 *           plus a second call for joint_owner_id if present.
 *
 * Models that register this observer (per observer docblock):
 *   - Property, Mortgage, SavingsAccount, InvestmentAccount, DCPension,
 *     BusinessInterest, Chattel, Estate\Asset, Estate\Liability
 *
 * G-1-b implementer: spy on NetWorthService, trigger one model per type,
 * assert invalidateCache($userId) is called once per fire — and twice
 * (once for user_id, once for joint_owner_id) on joint records.
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

it('invalidates cache on SavingsAccount create (single-owner)')
    ->todo('G-1-b: SavingsAccount::factory()->create([user_id => $user->id]); $netWorthSpy->shouldHaveReceived("invalidateCache")->with($user->id)->once()');

it('invalidates cache TWICE on joint SavingsAccount create (user + joint owner)')
    ->todo('G-1-b: joint account with joint_owner_id; assert invalidateCache called for both ids');

it('invalidates cache on SavingsAccount update')
    ->todo('G-1-b: existing $account->update([current_balance => ...]); assert invalidateCache called');

it('invalidates cache on SavingsAccount delete')
    ->todo('G-1-b: existing $account->delete(); assert invalidateCache called');

it('invalidates cache for every registered model type')
    ->todo('G-1-b: smoke-loop over the 9 model types listed in the observer docblock');
