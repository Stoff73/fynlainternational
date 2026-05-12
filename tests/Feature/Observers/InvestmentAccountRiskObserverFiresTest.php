<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\Investment\InvestmentAccount;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b firing tests for InvestmentAccountRiskObserver.
 *
 * Observer: packs/country-gb/src/Observers/InvestmentAccountRiskObserver.php
 *
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

it('fires on create', function () {
    InvestmentAccount::factory()->create(['user_id' => $this->user->id]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('fires on update when current_value changes', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 50000,
    ]);

    Bus::fake();
    Cache::flush();

    $account->update(['current_value' => 55000]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('does NOT fire on update when current_value is unchanged', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'account_name' => 'Original',
    ]);

    Bus::fake();
    Cache::flush();

    $account->update(['account_name' => 'Renamed']);

    Bus::assertNotDispatched(RecalculateRiskProfileJob::class);
});

it('fires on delete', function () {
    $account = InvestmentAccount::factory()->create(['user_id' => $this->user->id]);

    Bus::fake();
    Cache::flush();

    $account->delete();

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});
