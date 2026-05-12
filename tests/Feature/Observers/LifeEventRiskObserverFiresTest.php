<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\LifeEvent;
use Fynla\Core\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b firing tests for LifeEventRiskObserver.
 *
 * Observer: app/Observers/LifeEventRiskObserver.php
 *
 * Fires on: created / updated / deleted, gated by event_type ∈
 *   {wedding, redundancy_payment, inheritance, business_sale,
 *    property_sale, pension_lump_sum, large_purchase}.
 *
 * Dispatches: RecalculateRiskProfileJob via parent RiskRecalculationObserver.
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
    $this->user = User::factory()->create();
});

it('fires on create for a risk-relevant event_type', function () {
    LifeEvent::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'wedding',
    ]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('does NOT fire on create for a non-risk-relevant event_type', function () {
    LifeEvent::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'gift_received',
    ]);

    Bus::assertNotDispatched(RecalculateRiskProfileJob::class);
});

it('fires on update of a risk-relevant event', function () {
    $event = LifeEvent::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'inheritance',
    ]);

    Bus::fake();
    Cache::flush();

    $event->update(['description' => 'Updated description']);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('fires on delete of a risk-relevant event', function () {
    $event = LifeEvent::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'property_sale',
    ]);

    Bus::fake();
    Cache::flush();

    $event->delete();

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});
