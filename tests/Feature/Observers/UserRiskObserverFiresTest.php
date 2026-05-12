<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b scaffold for UserRiskObserver firing tests.
 *
 * Observer: app/Observers/UserRiskObserver.php
 * Fires on: User::updated only, AND only when a "relevantFields" column
 *           is among the changed fields. Created/deleted are NOT observed.
 *
 * Relevant fields (per observer):
 *   - annual_employment_income
 *   - annual_self_employment_income
 *   - annual_rental_income
 *   - annual_dividend_income
 *   - annual_interest_income
 *   - annual_other_income
 *   - annual_trust_income
 *   - monthly_expenditure
 *   - employment_status
 *   - target_retirement_age
 *   - date_of_birth
 *
 * Dispatches: RecalculateRiskProfileJob via parent RiskRecalculationObserver.
 *
 * G-1-b implementer: replace todo() with assertion that the job is/is-not
 * dispatched based on which field changed.
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
    $this->user = User::factory()->create();
});

it('fires on update of annual_employment_income')
    ->todo('G-1-b: $user->update([annual_employment_income => 60000]); Bus::assertDispatched(RecalculateRiskProfileJob::class)');

it('fires on update of monthly_expenditure')
    ->todo('G-1-b: $user->update([monthly_expenditure => 2500]); Bus::assertDispatched');

it('does NOT fire on update of unrelated field (e.g. first_name)')
    ->todo('G-1-b: $user->update([first_name => "X"]); Bus::assertNotDispatched');
