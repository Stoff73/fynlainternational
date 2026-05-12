<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\FamilyMember;
use Fynla\Core\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b firing tests for FamilyMemberRiskObserver.
 *
 * Observer: app/Observers/FamilyMemberRiskObserver.php
 *
 * Fires on:
 *   - created: only when is_dependent = true
 *   - updated: only when is_dependent is in the changeset
 *   - deleted: only when is_dependent was true
 *
 * Dispatches: RecalculateRiskProfileJob via parent RiskRecalculationObserver.
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
    $this->user = User::factory()->create();
});

it('fires on create when is_dependent = true', function () {
    FamilyMember::factory()->create([
        'user_id' => $this->user->id,
        'is_dependent' => true,
    ]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('does NOT fire on create when is_dependent = false', function () {
    FamilyMember::factory()->create([
        'user_id' => $this->user->id,
        'is_dependent' => false,
    ]);

    Bus::assertNotDispatched(RecalculateRiskProfileJob::class);
});

it('fires on update when is_dependent changes', function () {
    $member = FamilyMember::factory()->create([
        'user_id' => $this->user->id,
        'is_dependent' => false,
    ]);

    Bus::fake();
    Cache::flush();

    $member->update(['is_dependent' => true]);

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});

it('fires on delete when is_dependent was true', function () {
    $member = FamilyMember::factory()->create([
        'user_id' => $this->user->id,
        'is_dependent' => true,
    ]);

    Bus::fake();
    Cache::flush();

    $member->delete();

    Bus::assertDispatched(RecalculateRiskProfileJob::class);
});
