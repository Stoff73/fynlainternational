<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b scaffold for SavingsAccountRiskObserver firing tests.
 *
 * Observer: packs/country-gb/src/Observers/SavingsAccountRiskObserver.php
 * Fires on:
 *   - created: only when is_emergency_fund = true
 *   - updated: only when (current_balance OR is_emergency_fund) changes
 *     AND the account is or was an emergency fund
 *   - deleted: only when is_emergency_fund was true
 *
 * Dispatches: RecalculateRiskProfileJob via parent RiskRecalculationObserver.
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
    $this->user = User::factory()->create();
});

it('fires on create of an emergency-fund SavingsAccount')
    ->todo('G-1-b: SavingsAccount::factory()->create([is_emergency_fund => true]); Bus::assertDispatched');

it('does NOT fire on create of a non-emergency-fund SavingsAccount')
    ->todo('G-1-b: SavingsAccount::factory()->create([is_emergency_fund => false]); Bus::assertNotDispatched');

it('fires on update of an emergency-fund balance')
    ->todo('G-1-b: emergency $account->update([current_balance => +1000]); Bus::assertDispatched');

it('fires on toggle of is_emergency_fund (false → true)')
    ->todo('G-1-b: $account->update([is_emergency_fund => true]); Bus::assertDispatched (account WAS not, now is)');

it('does NOT fire on balance change of a non-emergency-fund account')
    ->todo('G-1-b: non-emergency $account->update([current_balance => ...]); Bus::assertNotDispatched');

it('fires on delete of an emergency-fund account')
    ->todo('G-1-b: emergency $account->delete(); Bus::assertDispatched');
