<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\DCPension;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b firing tests for RiskRecalculationObserver (abstract base class).
 *
 * Class: app/Observers/RiskRecalculationObserver.php
 *
 * This is the parent class for *RiskObserver subclasses. It doesn't register
 * itself on any model; its job is to provide a debounced
 * `dispatchRecalculation(int $userId, string $trigger)` helper that the 6
 * subclasses call. Behaviour verified here applies uniformly to all subclasses.
 *
 * Driven via DCPensionRiskObserver as the concrete trigger (it dispatches
 * unconditionally on create/delete, so it's the cleanest harness for
 * exercising the parent's logic).
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

it('dispatches RecalculateRiskProfileJob when no debounce key is set', function () {
    DCPension::factory()->create(['user_id' => $this->user->id]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('skips dispatch when the per-user debounce cache key is already present', function () {
    // Manually set the debounce cache key the parent observer checks for.
    Cache::put("risk_recalc_pending_{$this->user->id}", true, 5);

    DCPension::factory()->create(['user_id' => $this->user->id]);

    Bus::assertNotDispatched(RecalculateRiskProfileJob::class);
});

it('sets the debounce cache key on dispatch', function () {
    expect(Cache::has("risk_recalc_pending_{$this->user->id}"))->toBeFalse();

    DCPension::factory()->create(['user_id' => $this->user->id]);

    expect(Cache::has("risk_recalc_pending_{$this->user->id}"))->toBeTrue();
});

it('debounce is isolated per user — second user dispatches even when first user is debounced', function () {
    // First user has debounce active.
    Cache::put("risk_recalc_pending_{$this->user->id}", true, 5);

    // First user's pension does NOT dispatch.
    DCPension::factory()->create(['user_id' => $this->user->id]);
    Bus::assertDispatchedTimes(RecalculateRiskProfileJob::class, 0);

    // Second user has no debounce — their pension DOES dispatch.
    DCPension::factory()->create(['user_id' => $this->otherUser->id]);
    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});
