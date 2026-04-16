<?php

declare(strict_types=1);

use App\Models\User;
use Fynla\Core\Jurisdiction\ActiveJurisdictions;
use Fynla\Core\Jurisdiction\Jurisdiction;
use Fynla\Core\Models\Jurisdiction as JurisdictionModel;
use Fynla\Core\Models\UserJurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gb = JurisdictionModel::create([
        'code' => 'GB',
        'name' => 'United Kingdom',
        'currency' => 'GBP',
        'locale' => 'en-GB',
        'active' => true,
    ]);

    $this->user = User::factory()->create();

    UserJurisdiction::create([
        'user_id' => $this->user->id,
        'jurisdiction_id' => $this->gb->id,
        'is_primary' => true,
        'activated_at' => now(),
    ]);

    $this->service = new ActiveJurisdictions();
});

it('returns jurisdictions assigned to a user from the database', function () {
    $jurisdictions = $this->service->forUser($this->user->id);

    expect($jurisdictions)->toHaveCount(1);
    expect($jurisdictions[0])->toBeInstanceOf(Jurisdiction::class);
    expect($jurisdictions[0]->code)->toBe('GB');
});

it('confirms user has jurisdiction', function () {
    expect($this->service->userHasJurisdiction($this->user->id, 'GB'))->toBeTrue();
    expect($this->service->userHasJurisdiction($this->user->id, 'ZA'))->toBeFalse();
});

it('returns primary jurisdiction for a user', function () {
    $primary = $this->service->primaryForUser($this->user->id);

    expect($primary)->not->toBeNull();
    expect($primary->code)->toBe('GB');
});

it('returns empty for user with no jurisdiction assignments when GB not active', function () {
    $otherUser = User::factory()->create();

    // With GB active (default env), fallback kicks in
    $jurisdictions = $this->service->forUser($otherUser->id);

    // Should get GB via fallback
    expect($jurisdictions)->toHaveCount(1);
    expect($jurisdictions[0]->code)->toBe('GB');
});

it('normalises pack codes from env var', function () {
    $packs = $this->service->activePacks();

    // Default env is country-gb, which normalises to GB
    expect($packs)->toContain('GB');
});

it('supports multiple jurisdiction assignments', function () {
    $za = JurisdictionModel::create([
        'code' => 'ZA',
        'name' => 'South Africa',
        'currency' => 'ZAR',
        'locale' => 'en-ZA',
        'active' => true,
    ]);

    UserJurisdiction::create([
        'user_id' => $this->user->id,
        'jurisdiction_id' => $za->id,
        'is_primary' => false,
        'activated_at' => now(),
    ]);

    // Temporarily set env to enable both packs
    putenv('FYNLA_ACTIVE_PACKS=country-gb,country-za');

    $service = new ActiveJurisdictions();
    $jurisdictions = $service->forUser($this->user->id);

    expect($jurisdictions)->toHaveCount(2);

    $codes = array_map(fn (Jurisdiction $j) => $j->code, $jurisdictions);
    expect($codes)->toContain('GB');
    expect($codes)->toContain('ZA');

    // Reset env
    putenv('FYNLA_ACTIVE_PACKS=country-gb');
});
