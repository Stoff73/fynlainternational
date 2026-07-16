<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Core\Models\Jurisdiction;
use Fynla\Core\Models\UserJurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gb = Jurisdiction::create([
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
});

it('user has jurisdictions relationship via BelongsToMany', function () {
    $jurisdictions = $this->user->jurisdictions;

    expect($jurisdictions)->toHaveCount(1);
    expect($jurisdictions->first()->code)->toBe('GB');
});

it('pivot includes is_primary and activated_at', function () {
    $jurisdiction = $this->user->jurisdictions->first();

    expect($jurisdiction->pivot->is_primary)->toBeTruthy();
    expect($jurisdiction->pivot->activated_at)->not->toBeNull();
});

it('user primaryJurisdiction returns the primary', function () {
    $primary = $this->user->primaryJurisdiction();

    expect($primary)->not->toBeNull();
    expect($primary->code)->toBe('GB');
});

it('jurisdiction has users relationship', function () {
    $users = $this->gb->users;

    expect($users)->toHaveCount(1);
    expect($users->first()->id)->toBe($this->user->id);
});

it('UserJurisdiction belongs to user and jurisdiction', function () {
    $assignment = UserJurisdiction::first();

    expect($assignment->user->id)->toBe($this->user->id);
    expect($assignment->jurisdiction->code)->toBe('GB');
});

it('enforces unique user-jurisdiction composite', function () {
    expect(fn () => UserJurisdiction::create([
        'user_id' => $this->user->id,
        'jurisdiction_id' => $this->gb->id,
        'is_primary' => false,
        'activated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('mass-assigns and casts deactivated_at and auto_detected (WS1)', function () {
    $other = User::factory()->create();

    $row = UserJurisdiction::create([
        'user_id' => $other->id,
        'jurisdiction_id' => $this->gb->id,
        'is_primary' => true,
        'activated_at' => now(),
        'deactivated_at' => now(),
        'auto_detected' => true,
    ]);

    expect($row->fresh()->deactivated_at)->not->toBeNull()
        ->and($row->fresh()->auto_detected)->toBeTrue();
});
