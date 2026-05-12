<?php

declare(strict_types=1);

use App\Jobs\RecalculateRiskProfileJob;
use Fynla\Core\Models\FamilyMember;
use Fynla\Core\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * G-1-b scaffold for FamilyMemberRiskObserver firing tests.
 *
 * Observer: app/Observers/FamilyMemberRiskObserver.php
 * Fires on:
 *   - created: only when is_dependent = true
 *   - updated: only when is_dependent column is in the changeset
 *   - deleted: only when is_dependent was true
 *
 * Dispatches: RecalculateRiskProfileJob via parent RiskRecalculationObserver.
 *
 * G-1-b implementer: drive each scenario via FamilyMember factory states.
 */

beforeEach(function () {
    Bus::fake();
    Cache::flush();
    $this->user = User::factory()->create();
});

it('fires on create when is_dependent = true')
    ->todo('G-1-b: FamilyMember::factory()->create([is_dependent => true]); Bus::assertDispatched');

it('does NOT fire on create when is_dependent = false')
    ->todo('G-1-b: FamilyMember::factory()->create([is_dependent => false]); Bus::assertNotDispatched');

it('fires on update when is_dependent changes')
    ->todo('G-1-b: existing $member->update([is_dependent => !current]); Bus::assertDispatched');

it('fires on delete when is_dependent was true')
    ->todo('G-1-b: dependent $member->delete(); Bus::assertDispatched');
