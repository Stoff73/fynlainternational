<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(\Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

function compliantAllocation(): array
{
    return [
        'offshore' => 25,
        'equity' => 60,
        'property' => 5,
        'private_equity' => 5,
        'commodities' => 2,
        'hedge_funds' => 2,
        'other' => 1,
        'single_entity' => 4,
    ];
}

it('passes a fully compliant allocation', function () {
    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/reg28/check', [
        'tax_year' => '2026/27',
        'allocation' => compliantAllocation(),
    ]);

    $response->assertOk()
        ->assertJsonPath('data.compliant', true)
        ->assertJsonPath('data.breaches', []);
});

it('flags offshore breach over the limit', function () {
    $a = compliantAllocation();
    $a['offshore'] = 50;
    $a['equity'] = 35;

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/reg28/check', [
        'tax_year' => '2026/27',
        'allocation' => $a,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.compliant', false)
        ->assertJsonFragment(['offshore']);
});

it('flags equity breach over 75 percent', function () {
    $a = [
        'offshore' => 5,
        'equity' => 85,
        'property' => 0,
        'private_equity' => 5,
        'commodities' => 0,
        'hedge_funds' => 0,
        'other' => 5,
        'single_entity' => 3,
    ];

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/reg28/check', [
        'tax_year' => '2026/27',
        'allocation' => $a,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.compliant', false);
});

it('returns 422 when asset classes do not sum to 100 percent', function () {
    $a = compliantAllocation();
    $a['equity'] = 50;

    $this->actingAs($this->user)->postJson('/api/za/retirement/reg28/check', [
        'tax_year' => '2026/27',
        'allocation' => $a,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['allocation']);
});

it('persists a Reg 28 snapshot and lists it', function () {
    $this->actingAs($this->user)->postJson('/api/za/retirement/reg28/snapshots', [
        'tax_year' => '2026/27',
        'allocation' => compliantAllocation(),
    ])->assertCreated();

    $this->actingAs($this->user)->getJson('/api/za/retirement/reg28/snapshots?tax_year=2026/27')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('isolates snapshots between users', function () {
    $other = User::factory()->create();

    $this->actingAs($other)->postJson('/api/za/retirement/reg28/snapshots', [
        'tax_year' => '2026/27',
        'allocation' => compliantAllocation(),
    ])->assertCreated();

    $this->actingAs($this->user)->getJson('/api/za/retirement/reg28/snapshots')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
