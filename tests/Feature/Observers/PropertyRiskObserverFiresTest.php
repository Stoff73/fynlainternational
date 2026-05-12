<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\Property;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b firing tests for PropertyRiskObserver.
 *
 * Observer: packs/country-gb/src/Observers/PropertyRiskObserver.php
 *
 * Fires on:
 *   - created: unconditional
 *   - updated: only when ANY of (current_value, purchase_price,
 *     ownership_percentage) is dirty
 *   - deleted: unconditional
 *
 * Dispatches: RecalculateRiskProfileJob via parent RiskRecalculationObserver.
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
    $this->user = User::factory()->create();
});

it('fires on create', function () {
    Property::factory()->create(['user_id' => $this->user->id]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('fires on update when current_value changes', function () {
    $property = Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000,
    ]);

    Bus::fake();
    Cache::flush();

    $property->update(['current_value' => 450000]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('fires on update when purchase_price changes', function () {
    $property = Property::factory()->create([
        'user_id' => $this->user->id,
        'purchase_price' => 350000,
    ]);

    Bus::fake();
    Cache::flush();

    $property->update(['purchase_price' => 360000]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('fires on update when ownership_percentage changes', function () {
    $property = Property::factory()->create([
        'user_id' => $this->user->id,
        'ownership_percentage' => 100.00,
    ]);

    Bus::fake();
    Cache::flush();

    $property->update(['ownership_percentage' => 50.00]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('does NOT fire on update when only a non-risk-relevant field changes', function () {
    $property = Property::factory()->create([
        'user_id' => $this->user->id,
        'address_line_1' => '1 Main St',
    ]);

    Bus::fake();
    Cache::flush();

    $property->update(['address_line_1' => '2 New Street']);

    Bus::assertNotDispatched(RecalculateRiskProfileJob::class);
});

it('fires on delete', function () {
    $property = Property::factory()->create(['user_id' => $this->user->id]);

    Bus::fake();
    Cache::flush();

    $property->delete();

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});
