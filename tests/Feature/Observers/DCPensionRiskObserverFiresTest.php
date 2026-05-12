<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\DCPension;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b firing tests for DCPensionRiskObserver.
 *
 * Observer: packs/country-gb/src/Observers/DCPensionRiskObserver.php
 * Registered: app/Providers/EventServiceProvider.php:64
 *
 * Fires on:
 *   - created: unconditional
 *   - updated: only when current_fund_value is in the changeset
 *   - deleted: unconditional
 *
 * Dispatches: RecalculateRiskProfileJob via parent RiskRecalculationObserver,
 * gated by a 5-second per-user debounce cache key.
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
    $this->user = User::factory()->create();
});

it('fires on create', function () {
    DCPension::factory()->create(['user_id' => $this->user->id]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('fires on update when current_fund_value changes', function () {
    $pension = DCPension::factory()->create([
        'user_id' => $this->user->id,
        'current_fund_value' => 100000,
    ]);

    Bus::fake();
    Cache::flush();

    $pension->update(['current_fund_value' => 110000]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('does NOT fire on update when current_fund_value is unchanged', function () {
    $pension = DCPension::factory()->create([
        'user_id' => $this->user->id,
        'provider' => 'Aviva',
    ]);

    Bus::fake();
    Cache::flush();

    $pension->update(['provider' => 'Scottish Widows']);

    Bus::assertNotDispatched(RecalculateRiskProfileJob::class);
});

it('fires on delete', function () {
    $pension = DCPension::factory()->create(['user_id' => $this->user->id]);

    Bus::fake();
    Cache::flush();

    $pension->delete();

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});
