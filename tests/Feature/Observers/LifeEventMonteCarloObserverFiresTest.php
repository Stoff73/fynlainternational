<?php

declare(strict_types=1);

use App\Services\Cache\CacheInvalidationService;
use Fynla\Core\Models\LifeEvent;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Goals\GoalsProjectionService;
use Fynla\Packs\Gb\Investment\MonteCarloSimulator;

/**
 * G-1-b firing tests for LifeEventMonteCarloObserver.
 *
 * Observer: app/Observers/LifeEventMonteCarloObserver.php
 *
 * Fires on: LifeEvent::created / updated / deleted — unconditionally.
 *
 * Side effects (per fire):
 *   - MonteCarloSimulator::clearUserCache($userId)
 *   - GoalsProjectionService::clearCache($userId)
 *   - CacheInvalidationService::invalidateForUser($userId)
 *
 * The three collaborators are spied so the real (expensive) Monte Carlo +
 * goals projection aren't invoked. Assertions are method-call assertions.
 */

beforeEach(function () {
    $this->user = User::factory()->create();

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

it('clears Monte Carlo + goals + cache invalidation caches on LifeEvent create', function () {
    LifeEvent::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'inheritance',
    ]);

    $this->simulatorSpy->shouldHaveReceived('clearUserCache')->with($this->user->id);
    $this->projectionSpy->shouldHaveReceived('clearCache')->with($this->user->id);
    $this->cacheInvalidationSpy->shouldHaveReceived('invalidateForUser')->with($this->user->id);
});

it('clears the three caches on LifeEvent update', function () {
    $event = LifeEvent::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'inheritance',
    ]);

    $event->update(['description' => 'Updated description']);

    $this->simulatorSpy->shouldHaveReceived('clearUserCache')->with($this->user->id)->twice();
    $this->projectionSpy->shouldHaveReceived('clearCache')->with($this->user->id)->twice();
    $this->cacheInvalidationSpy->shouldHaveReceived('invalidateForUser')->with($this->user->id)->twice();
});

it('clears the three caches on LifeEvent delete', function () {
    $event = LifeEvent::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'inheritance',
    ]);

    $event->delete();

    $this->simulatorSpy->shouldHaveReceived('clearUserCache')->with($this->user->id)->twice();
    $this->projectionSpy->shouldHaveReceived('clearCache')->with($this->user->id)->twice();
    $this->cacheInvalidationSpy->shouldHaveReceived('invalidateForUser')->with($this->user->id)->twice();
});
