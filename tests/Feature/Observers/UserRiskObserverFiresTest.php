<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b firing tests for UserRiskObserver.
 *
 * Observer: app/Observers/UserRiskObserver.php
 *
 * Fires on: User::updated only, AND only when a "relevantFields" column is
 *           among the changed fields. Created/deleted are NOT observed.
 *
 * Relevant fields (per observer):
 *   annual_employment_income, annual_self_employment_income,
 *   annual_rental_income, annual_dividend_income, annual_interest_income,
 *   annual_other_income, annual_trust_income, monthly_expenditure,
 *   employment_status, target_retirement_age, date_of_birth
 *
 * Dispatches: RecalculateRiskProfileJob via parent RiskRecalculationObserver.
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
    $this->user = User::factory()->create();
});

it('fires on update of annual_employment_income', function () {
    Bus::fake();
    Cache::flush();

    $this->user->update(['annual_employment_income' => 60000]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('fires on update of monthly_expenditure', function () {
    Bus::fake();
    Cache::flush();

    $this->user->update(['monthly_expenditure' => 2500]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('does NOT fire on update of unrelated field (e.g. first_name)', function () {
    Bus::fake();
    Cache::flush();

    $this->user->update(['first_name' => 'Renamed']);

    Bus::assertNotDispatched(RecalculateRiskProfileJob::class);
});
