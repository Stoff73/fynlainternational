<?php

declare(strict_types=1);

use App\Models\User;
use Fynla\Core\Models\Jurisdiction;
use Fynla\Core\Models\UserJurisdiction;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Ensure the GB jurisdiction seeded by Jurisdictions backfill is available.
    $this->gb = Jurisdiction::firstOrCreate(
        ['code' => 'GB'],
        ['name' => 'United Kingdom', 'currency' => 'GBP', 'locale' => 'en-GB', 'active' => true]
    );
});

it('session endpoint returns active_jurisdictions and cross_border for a GB-only user', function () {
    $user = User::factory()->create();
    UserJurisdiction::create([
        'user_id' => $user->id,
        'jurisdiction_id' => $this->gb->id,
        'is_primary' => true,
        'activated_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/auth/user');

    $response->assertOk()
        ->assertJsonPath('data.active_jurisdictions', ['gb'])
        ->assertJsonPath('data.primary_jurisdiction', 'gb')
        ->assertJsonPath('data.cross_border', false);
});

it('session endpoint flags cross_border for a user in two jurisdictions', function () {
    $za = Jurisdiction::firstOrCreate(
        ['code' => 'ZA'],
        ['name' => 'South Africa', 'currency' => 'ZAR', 'locale' => 'en-ZA', 'active' => true]
    );

    $user = User::factory()->create();
    UserJurisdiction::create([
        'user_id' => $user->id,
        'jurisdiction_id' => $this->gb->id,
        'is_primary' => true,
        'activated_at' => now(),
    ]);
    UserJurisdiction::create([
        'user_id' => $user->id,
        'jurisdiction_id' => $za->id,
        'is_primary' => false,
        'activated_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/auth/user');

    $response->assertOk()
        ->assertJsonPath('data.primary_jurisdiction', 'gb')
        ->assertJsonPath('data.cross_border', true);

    expect($response->json('data.active_jurisdictions'))
        ->toContain('gb')
        ->toContain('za');
});

it('session endpoint returns empty jurisdictions list for users without any assignment', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/auth/user');

    $response->assertOk()
        ->assertJsonPath('data.active_jurisdictions', [])
        ->assertJsonPath('data.primary_jurisdiction', null)
        ->assertJsonPath('data.cross_border', false);
});
