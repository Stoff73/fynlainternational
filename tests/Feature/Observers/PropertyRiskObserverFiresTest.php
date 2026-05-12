<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\Property;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b scaffold for PropertyRiskObserver firing tests.
 *
 * Observer: packs/country-gb/src/Observers/PropertyRiskObserver.php
 * Fires on:
 *   - created: unconditional
 *   - updated: only when ANY risk-relevant field is dirty
 *     (current_value, purchase_price, ownership_percentage)
 *   - deleted: unconditional
 *
 * Dispatches: RecalculateRiskProfileJob via parent RiskRecalculationObserver.
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
    $this->user = User::factory()->create();
});

it('fires on create')
    ->todo('G-1-b: Property::factory()->create([user_id => $user->id]); Bus::assertDispatched');

it('fires on update when current_value changes')
    ->todo('G-1-b: existing $property->update([current_value => +50000]); Bus::assertDispatched');

it('fires on update when purchase_price changes')
    ->todo('G-1-b: existing $property->update([purchase_price => ...]); Bus::assertDispatched');

it('fires on update when ownership_percentage changes')
    ->todo('G-1-b: existing $property->update([ownership_percentage => 60]); Bus::assertDispatched');

it('does NOT fire on update when only a non-risk-relevant field changes (e.g. address)')
    ->todo('G-1-b: existing $property->update([address => "X"]); Bus::assertNotDispatched');

it('fires on delete')
    ->todo('G-1-b: existing $property->delete(); Bus::assertDispatched');
