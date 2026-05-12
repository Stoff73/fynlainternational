<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\Investment\InvestmentAccount;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b scaffold for InvestmentAccountRiskObserver firing tests.
 *
 * Observer: packs/country-gb/src/Observers/InvestmentAccountRiskObserver.php
 * Fires on:
 *   - created: unconditional
 *   - updated: only when current_value is in the changeset
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
    ->todo('G-1-b: InvestmentAccount::factory()->create([user_id => $user->id]); Bus::assertDispatched(RecalculateRiskProfileJob::class)');

it('fires on update when current_value changes')
    ->todo('G-1-b: existing $account->update([current_value => +5000]); Bus::assertDispatched');

it('does NOT fire on update when current_value is unchanged')
    ->todo('G-1-b: existing $account->update([account_name => "X"]); Bus::assertNotDispatched');

it('fires on delete')
    ->todo('G-1-b: existing $account->delete(); Bus::assertDispatched');
