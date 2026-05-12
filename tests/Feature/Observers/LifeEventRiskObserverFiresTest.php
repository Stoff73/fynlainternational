<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\LifeEvent;
use Fynla\Core\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b scaffold for LifeEventRiskObserver firing tests.
 *
 * Observer: app/Observers/LifeEventRiskObserver.php
 * Fires on: created / updated / deleted, gated by event_type ∈ RISK_RELEVANT_TYPES.
 *
 * Risk-relevant event types (per observer):
 *   - wedding
 *   - redundancy_payment
 *   - inheritance
 *   - business_sale
 *   - property_sale
 *   - pension_lump_sum
 *   - large_purchase
 *
 * Dispatches: RecalculateRiskProfileJob via parent RiskRecalculationObserver.
 *
 * G-1-b implementer: drive each scenario via LifeEvent factory with
 * event_type values inside vs outside the relevant set.
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
    $this->user = User::factory()->create();
});

it('fires on create for a risk-relevant event_type')
    ->todo('G-1-b: LifeEvent::factory()->create([event_type => "wedding"]); Bus::assertDispatched');

it('does NOT fire on create for a non-risk-relevant event_type')
    ->todo('G-1-b: LifeEvent::factory()->create([event_type => "birthday"]); Bus::assertNotDispatched');

it('fires on update of a risk-relevant event')
    ->todo('G-1-b: existing risk-relevant $event->update([...]); Bus::assertDispatched');

it('fires on delete of a risk-relevant event')
    ->todo('G-1-b: risk-relevant $event->delete(); Bus::assertDispatched');
