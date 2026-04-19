<?php

declare(strict_types=1);

use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(ZaTaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/za/exchange-control/dashboard')->assertStatus(401);
});

it('returns SDA + FIA caps with consumed and remaining for current calendar year', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/za/exchange-control/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'calendar_year',
                'allowances' => [
                    'sda' => ['type', 'annual_limit', 'currency', 'description'],
                    'fia' => ['type', 'annual_limit', 'currency', 'description'],
                ],
                'consumed' => ['sda_minor', 'fia_minor', 'total_minor'],
                'remaining' => ['sda_minor', 'fia_minor'],
                'sarb_threshold_minor',
            ],
        ]);

    expect($response->json('data.allowances.sda.annual_limit'))->toBe(200_000_000); // R2m
    expect($response->json('data.allowances.fia.annual_limit'))->toBe(1_000_000_000); // R10m
    expect($response->json('data.consumed.sda_minor'))->toBe(0);
    expect($response->json('data.remaining.sda_minor'))->toBe(200_000_000);
});

it('records an SDA transfer and increments consumed', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/za/exchange-control/transfers', [
        'allowance_type' => 'sda',
        'amount_minor' => 50_000_000, // R500,000
        'transfer_date' => '2026-04-15',
        'destination_country' => 'United Kingdom',
        'purpose' => 'Property purchase',
    ]);

    $response->assertStatus(201);
    $dashboard = $this->getJson('/api/za/exchange-control/dashboard?calendar_year=2026')->json();
    expect($dashboard['data']['consumed']['sda_minor'])->toBe(50_000_000);
    expect($dashboard['data']['remaining']['sda_minor'])->toBe(150_000_000);
});

it('records an FIA transfer with AIT metadata', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/za/exchange-control/transfers', [
        'allowance_type' => 'fia',
        'amount_minor' => 300_000_000, // R3m
        'transfer_date' => '2026-04-20',
        'destination_country' => 'United States',
        'purpose' => 'Investment portfolio diversification',
        'authorised_dealer' => 'Investec',
        'recipient_account' => 'US-IBAN-123',
        'ait_reference' => 'AIT-2026-0042',
        'ait_documents' => [
            'tax_clearance_issued' => true,
            'source_of_funds_documented' => true,
            'recipient_kyc_complete' => true,
            'dealer_notified' => true,
        ],
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('za_exchange_control_ledger', [
        'user_id' => $this->user->id,
        'allowance_type' => 'fia',
        'amount_minor' => 300_000_000,
        'ait_reference' => 'AIT-2026-0042',
    ]);
});

it('isolates calendar year consumption', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/za/exchange-control/transfers', [
        'allowance_type' => 'sda',
        'amount_minor' => 100_000_000,
        'transfer_date' => '2025-12-15',
    ])->assertStatus(201);

    $dashboard = $this->getJson('/api/za/exchange-control/dashboard?calendar_year=2026')->json();
    expect($dashboard['data']['consumed']['sda_minor'])->toBe(0);

    $dashboard2025 = $this->getJson('/api/za/exchange-control/dashboard?calendar_year=2025')->json();
    expect($dashboard2025['data']['consumed']['sda_minor'])->toBe(100_000_000);
});

it('checks approval requirement for what-if scenario', function () {
    Sanctum::actingAs($this->user);

    $small = $this->postJson('/api/za/exchange-control/check-approval', [
        'amount_minor' => 100_000_000, // R1m — under SDA
        'type' => 'investment',
    ]);
    $small->assertOk();
    expect($small->json('data.requires_approval'))->toBeFalse();

    $large = $this->postJson('/api/za/exchange-control/check-approval', [
        'amount_minor' => 1_500_000_000, // R15m — above SARB threshold
        'type' => 'investment',
    ]);
    $large->assertOk();
    expect($large->json('data.requires_approval'))->toBeTrue();
});

it('rejects an invalid allowance_type on transfer', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/za/exchange-control/transfers', [
        'allowance_type' => 'bogus',
        'amount_minor' => 50_000_000,
        'transfer_date' => '2026-04-15',
    ]);

    $response->assertStatus(422);
});

it('lists transfers for the current calendar year', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/za/exchange-control/transfers', [
        'allowance_type' => 'sda',
        'amount_minor' => 50_000_000,
        'transfer_date' => '2026-04-15',
        'destination_country' => 'Ireland',
    ])->assertStatus(201);

    $response = $this->getJson('/api/za/exchange-control/transfers?calendar_year=2026');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'allowance_type', 'amount_minor', 'transfer_date']]]);

    expect($response->json('data'))->toHaveCount(1);
});
