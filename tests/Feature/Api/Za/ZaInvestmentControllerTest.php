<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
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
    $this->getJson('/api/za/investments/dashboard')->assertStatus(401);
});

it('returns wrappers + allowances + open lots summary on dashboard', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/za/investments/dashboard?tax_year=2026/27');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'tax_year',
                'wrappers' => [['code', 'name', 'description', 'tax_treatment']],
                'allowances' => ['tfsa', 'discretionary', 'endowment'],
                'open_lot_summary' => ['total_open_cost_basis_minor', 'lot_count'],
            ],
        ]);

    expect($response->json('data.wrappers'))->toHaveCount(3);
    expect($response->json('data.open_lot_summary.lot_count'))->toBe(0);
    expect($response->json('data.open_lot_summary.total_open_cost_basis_minor'))->toBe(0);
});

it('stores a ZA investment account with country_code=ZA and country=South Africa', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/za/investments/accounts', [
        'account_type' => 'discretionary',
        'provider' => 'Allan Gray',
        'current_value' => 100000,
        'tax_year' => '2026/27',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('investment_accounts', [
        'user_id' => $this->user->id,
        'country_code' => 'ZA',
        'country' => 'South Africa',
        'account_type' => 'discretionary',
    ]);
});

it('records a purchase, writes a lot, and syncs holdings.cost_basis', function () {
    Sanctum::actingAs($this->user);

    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'country_code' => 'ZA',
        'account_type' => 'discretionary',
    ]);
    $holding = Holding::create([
        'holdable_id' => $account->id,
        'holdable_type' => InvestmentAccount::class,
        'asset_type' => 'equity',
        'security_name' => 'Naspers',
        'ticker' => 'NPN',
        'quantity' => 0,
        'cost_basis' => 0,
        'current_value' => 0,
    ]);

    $response = $this->postJson('/api/za/investments/holdings/purchase', [
        'holding_id' => $holding->id,
        'quantity' => 10,
        'cost_minor' => 5_000_000, // R50,000
        'acquisition_date' => '2026-04-01',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('za_holding_lots', [
        'user_id' => $this->user->id,
        'holding_id' => $holding->id,
        'quantity_acquired' => 10,
        'acquisition_cost_minor' => 5_000_000,
    ]);
    expect((float) $holding->fresh()->cost_basis)->toEqual(50000.00);
});

it('records a disposal and updates holdings.cost_basis to remaining open cost', function () {
    Sanctum::actingAs($this->user);

    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'country_code' => 'ZA',
        'account_type' => 'discretionary',
    ]);
    $holding = Holding::create([
        'holdable_id' => $account->id,
        'holdable_type' => InvestmentAccount::class,
        'asset_type' => 'equity',
        'security_name' => 'Naspers',
        'quantity' => 0,
        'cost_basis' => 0,
        'current_value' => 0,
    ]);

    $this->postJson('/api/za/investments/holdings/purchase', [
        'holding_id' => $holding->id,
        'quantity' => 10,
        'cost_minor' => 5_000_000,
        'acquisition_date' => '2026-04-01',
    ])->assertStatus(201);

    $response = $this->postJson('/api/za/investments/holdings/disposal', [
        'holding_id' => $holding->id,
        'quantity' => 5,
        'disposal_date' => '2026-04-15',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['units_disposed', 'cost_basis_removed_minor']]);

    // 5 remaining units at avg cost R5,000 each = R25,000 cost basis
    expect((float) $holding->fresh()->cost_basis)->toEqual(25000.00);
});

it('returns 422 when disposal exceeds open quantity', function () {
    Sanctum::actingAs($this->user);

    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'country_code' => 'ZA',
        'account_type' => 'discretionary',
    ]);
    $holding = Holding::create([
        'holdable_id' => $account->id,
        'holdable_type' => InvestmentAccount::class,
        'asset_type' => 'equity',
        'security_name' => 'Naspers',
        'quantity' => 0,
        'cost_basis' => 0,
        'current_value' => 0,
    ]);

    $this->postJson('/api/za/investments/holdings/purchase', [
        'holding_id' => $holding->id,
        'quantity' => 5,
        'cost_minor' => 1_000_000,
        'acquisition_date' => '2026-04-01',
    ])->assertStatus(201);

    $response = $this->postJson('/api/za/investments/holdings/disposal', [
        'holding_id' => $holding->id,
        'quantity' => 999,
        'disposal_date' => '2026-04-15',
    ]);

    $response->assertStatus(422);
});

it('lists open lots ordered by acquisition_date', function () {
    Sanctum::actingAs($this->user);

    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'country_code' => 'ZA',
        'account_type' => 'discretionary',
    ]);
    $holding = Holding::create([
        'holdable_id' => $account->id,
        'holdable_type' => InvestmentAccount::class,
        'asset_type' => 'equity',
        'security_name' => 'Naspers',
        'quantity' => 0,
        'cost_basis' => 0,
        'current_value' => 0,
    ]);
    $this->postJson('/api/za/investments/holdings/purchase', [
        'holding_id' => $holding->id, 'quantity' => 10, 'cost_minor' => 5_000_000, 'acquisition_date' => '2026-04-01',
    ]);

    $response = $this->getJson("/api/za/investments/holdings/{$holding->id}/lots");

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'quantity_open', 'acquisition_cost_minor', 'acquisition_date']]]);
});

it('calculates discretionary CGT for a what-if scenario', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/za/investments/cgt/calculate', [
        'wrapper_code' => 'discretionary',
        'gain_minor' => 10_000_000, // R100,000
        'income_minor' => 50_000_000, // R500,000
        'age' => 40,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['tax_due_minor', 'exclusion_applied_minor', 'included_minor', 'marginal_rate']]);

    expect($response->json('data.tax_due_minor'))->toBeGreaterThan(0);
});

it('calculates endowment CGT at flat wrapper rate', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/za/investments/cgt/calculate', [
        'wrapper_code' => 'endowment',
        'gain_minor' => 10_000_000,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['tax_due_minor', 'wrapper_rate_bps']]);

    expect($response->json('data.tax_due_minor'))->toBeGreaterThan(0);
});

it('returns zero CGT for TFSA wrapper', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/za/investments/cgt/calculate', [
        'wrapper_code' => 'tfsa',
        'gain_minor' => 10_000_000,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk();
    expect($response->json('data.tax_due_minor'))->toBe(0);
});
