<?php

declare(strict_types=1);

use Database\Seeders\JurisdictionSeeder;
use Fynla\Core\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaJurisdictionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(JurisdictionSeeder::class);
    $this->seed(ZaJurisdictionSeeder::class);
});

it('403s a GB-only user hitting a ZA endpoint (WS1 enforcement)', function () {
    $user = User::factory()->jurisdiction('GB')->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/za/goals/defaults')
        ->assertStatus(403)
        ->assertJson(['code' => 'JURISDICTION_NOT_AUTHORISED']);
});

it('fail-opens for a row-less user (no jurisdictions) on a pack route', function () {
    $user = User::factory()->create(); // deliberately row-less

    Sanctum::actingAs($user);

    expect($this->getJson('/api/za/goals/defaults')->status())->not->toBe(403);
});

it('does not 403 a ZA user hitting a ZA endpoint', function () {
    $user = User::factory()->jurisdiction('ZA')->create();

    Sanctum::actingAs($user);

    expect($this->getJson('/api/za/goals/defaults')->status())->not->toBe(403);
});

it('does not 403 any authenticated user on a core route', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    expect($this->getJson('/api/user/family-members')->status())->not->toBe(403);
});
