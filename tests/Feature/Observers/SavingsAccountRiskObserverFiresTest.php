<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b firing tests for SavingsAccountRiskObserver.
 *
 * Observer: packs/country-gb/src/Observers/SavingsAccountRiskObserver.php
 *
 * Fires on:
 *   - created: only when is_emergency_fund = true
 *   - updated: only when (current_balance OR is_emergency_fund) changes AND
 *     the account is or was an emergency fund
 *   - deleted: only when is_emergency_fund was true
 *
 * Dispatches: RecalculateRiskProfileJob via parent RiskRecalculationObserver.
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
    $this->user = User::factory()->create();
});

it('fires on create of an emergency-fund SavingsAccount', function () {
    SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'is_emergency_fund' => true,
    ]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('does NOT fire on create of a non-emergency-fund SavingsAccount', function () {
    SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'is_emergency_fund' => false,
    ]);

    Bus::assertNotDispatched(RecalculateRiskProfileJob::class);
});

it('fires on update of an emergency-fund balance', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'is_emergency_fund' => true,
        'current_balance' => 5000,
    ]);

    Bus::fake();
    Cache::flush();

    $account->update(['current_balance' => 6000]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('fires on toggle of is_emergency_fund (false → true)', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'is_emergency_fund' => false,
    ]);

    Bus::fake();
    Cache::flush();

    $account->update(['is_emergency_fund' => true]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('does NOT fire on balance change of a non-emergency-fund account', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'is_emergency_fund' => false,
        'current_balance' => 1000,
    ]);

    Bus::fake();
    Cache::flush();

    $account->update(['current_balance' => 1500]);

    Bus::assertNotDispatched(RecalculateRiskProfileJob::class);
});

it('fires on delete of an emergency-fund account', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'is_emergency_fund' => true,
    ]);

    Bus::fake();
    Cache::flush();

    $account->delete();

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});
