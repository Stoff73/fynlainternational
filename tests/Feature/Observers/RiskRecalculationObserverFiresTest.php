<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b scaffold for RiskRecalculationObserver (abstract base class).
 *
 * Class: app/Observers/RiskRecalculationObserver.php
 *
 * This is the parent class for all *RiskObserver subclasses. It doesn't
 * register itself on any model; its job is to provide a debounced
 * dispatchRecalculation(int $userId, string $trigger) helper that the 6
 * subclasses (UserRiskObserver, FamilyMemberRiskObserver, LifeEventRiskObserver,
 * DCPensionRiskObserver, InvestmentAccountRiskObserver, PropertyRiskObserver,
 * SavingsAccountRiskObserver) call.
 *
 * Tests below verify the SHARED behaviour:
 *   - dispatches RecalculateRiskProfileJob when no debounce key is set
 *   - skips when the per-user debounce cache key is present
 *   - sets the cache key on dispatch (TTL 5s)
 *   - ignores userId <= 0
 *
 * G-1-b implementer: replace each todo() with a real assertion. Since
 * dispatchRecalculation() is protected, drive it through any subclass
 * (e.g. UserRiskObserver via a User update on a relevant field) rather
 * than via reflection.
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
});

it('dispatches RecalculateRiskProfileJob with a 5s delay when no debounce key is set')
    ->todo('G-1-b: trigger via subclass; Bus::assertDispatched(RecalculateRiskProfileJob::class)');

it('skips dispatch when the per-user debounce cache key is already present')
    ->todo('G-1-b: pre-set Cache::put("risk_recalc_pending_{userId}", true, 5); trigger; Bus::assertNotDispatched');

it('sets the debounce cache key on dispatch with TTL 5s')
    ->todo('G-1-b: trigger; assert Cache::has("risk_recalc_pending_{userId}")');

it('ignores userId values of zero or below')
    ->todo('G-1-b: trigger with userId = 0; Bus::assertNotDispatched');
