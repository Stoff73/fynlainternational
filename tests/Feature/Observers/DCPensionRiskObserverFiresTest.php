<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\DCPension;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b scaffold for DCPensionRiskObserver firing tests.
 *
 * Observer: packs/country-gb/src/Observers/DCPensionRiskObserver.php
 * Fires on:
 *   - created: unconditional
 *   - updated: only when current_fund_value is in the changeset
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
    ->todo('G-1-b: DCPension::factory()->create([user_id => $user->id]); Bus::assertDispatched(RecalculateRiskProfileJob::class)');

it('fires on update when current_fund_value changes')
    ->todo('G-1-b: existing $pension->update([current_fund_value => +10000]); Bus::assertDispatched');

it('does NOT fire on update when current_fund_value is unchanged')
    ->todo('G-1-b: existing $pension->update([provider => "X"]); Bus::assertNotDispatched');

it('fires on delete')
    ->todo('G-1-b: existing $pension->delete(); Bus::assertDispatched');
