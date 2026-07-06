<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Database\Seeders\TaxConfigurationSeeder;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Fynla\Packs\Gb\NetWorth\NetWorthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Test Gauntlet G-2-e — cache poisoning from pre-relocation entries.
 *
 * A cache entry written before the pack relocation could contain legacy
 * App\Models\X FQCN strings. Post-deploy the app must either resolve them
 * (class_alias) or rebuild the entry gracefully — never 500.
 */
it('invalidates a poisoned net-worth cache entry when the underlying model mutates', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 1000,
    ]);

    // Poison: hand-write a stale entry at the net-worth cache key with a
    // legacy-namespace payload that a pre-relocation build might have cached.
    $cacheKey = "net_worth:user_{$user->id}:date_".Carbon::now()->toDateString();
    Cache::put($cacheKey, ['stale' => true, 'model' => 'App\\Models\\SavingsAccount'], 3600);
    expect(Cache::has($cacheKey))->toBeTrue();

    // Mutating the account fires NetWorthCacheObserver::updated → invalidate.
    $account->update(['current_balance' => 2000]);

    // The poisoned entry must be gone (invalidated), not served.
    expect(Cache::has($cacheKey))->toBeFalse();
});

it('resolves a legacy App\\Models\\User morph string via class_alias without error', function () {
    // The class_alias registered in CoreServiceProvider must map the legacy
    // FQCN to the core class, so a stale serialized reference resolves.
    expect(class_exists('App\\Models\\User'))->toBeTrue()
        ->and((new ReflectionClass('App\\Models\\User'))->getName())
        ->toBe(User::class);
});

it('rebuilds net worth cleanly for a user after cache invalidation', function () {
    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 3000,
    ]);

    $service = app(NetWorthService::class);

    // Prime, invalidate, re-read — must not throw and must return an array.
    $service->getCachedNetWorth($user);
    $service->invalidateCache($user->id);
    $rebuilt = $service->getCachedNetWorth($user);

    expect($rebuilt)->toBeArray()->toHaveKey('net_worth');
});
