<?php

declare(strict_types=1);

use App\Services\Cache\CacheInvalidationService;
use Fynla\Core\Models\LifeEvent;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Goals\GoalsProjectionService;
use Fynla\Packs\Gb\Investment\MonteCarloSimulator;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b scaffold for LifeEventMonteCarloObserver firing tests.
 *
 * Observer: app/Observers/LifeEventMonteCarloObserver.php
 * Fires on: LifeEvent::created / updated / deleted — unconditionally.
 *
 * Side effects:
 *   - MonteCarloSimulator::clearUserCache($userId)
 *   - GoalsProjectionService::clearCache($userId)
 *   - CacheInvalidationService::invalidateForUser($userId)
 *
 * G-1-b implementer: mock the three collaborators (or use Mockery::spy),
 * trigger the LifeEvent lifecycle, and assert clear/invalidate calls
 * for the user_id. Don't actually run a Monte Carlo simulation.
 */

beforeEach(function () {
    Cache::flush();
    $this->user = User::factory()->create();

    // Spy on the three collaborators so we can assert clear/invalidate calls
    // without exercising the real (expensive) Monte Carlo + goals projection.
    $this->simulatorSpy = Mockery::spy(MonteCarloSimulator::class);
    $this->projectionSpy = Mockery::spy(GoalsProjectionService::class);
    $this->cacheInvalidationSpy = Mockery::spy(CacheInvalidationService::class);
    app()->instance(MonteCarloSimulator::class, $this->simulatorSpy);
    app()->instance(GoalsProjectionService::class, $this->projectionSpy);
    app()->instance(CacheInvalidationService::class, $this->cacheInvalidationSpy);
});

afterEach(function () {
    Mockery::close();
});

it('clears Monte Carlo + goals + cache invalidation caches on LifeEvent create')
    ->todo('G-1-b: LifeEvent::factory()->create([user_id => $user->id]); assert all 3 spies received clear/invalidate with $user->id');

it('clears the three caches on LifeEvent update')
    ->todo('G-1-b: existing $event->update([...]); assert 3 spies received clear/invalidate');

it('clears the three caches on LifeEvent delete')
    ->todo('G-1-b: existing $event->delete(); assert 3 spies received clear/invalidate');
