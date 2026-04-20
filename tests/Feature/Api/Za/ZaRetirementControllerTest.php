<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
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

it('rejects unauthenticated dashboard requests', function () {
    $this->getJson('/api/za/retirement/dashboard')->assertStatus(401);
});

it('returns dashboard shape for authenticated ZA user', function () {
    $response = $this->actingAs($this->user)->getJson('/api/za/retirement/dashboard');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['tax_year', 'annual_allowance_minor', 'total_balance_minor', 'fund_count']]);
});

it('creates a retirement fund with country set to South Africa', function () {
    $payload = [
        'fund_type' => 'retirement_annuity',
        'provider' => 'Allan Gray',
        'scheme_name' => 'Allan Gray RA',
        'starting_vested_minor' => 0,
        'starting_savings_minor' => 0,
        'starting_retirement_minor' => 0,
    ];

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/funds', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.country_code', 'ZA')
        ->assertJsonPath('data.country', 'South Africa')
        ->assertJsonPath('data.fund_type_label', 'Retirement Annuity');
});

it('requires provident_vested_pre2021_minor when fund_type is provident_fund', function () {
    $payload = [
        'fund_type' => 'provident_fund',
        'provider' => 'Old Mutual',
        'starting_vested_minor' => 0,
        'starting_savings_minor' => 0,
        'starting_retirement_minor' => 0,
    ];

    $this->actingAs($this->user)->postJson('/api/za/retirement/funds', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['provident_vested_pre2021_minor']);
});

it('returns the four bucket balances for an owned fund', function () {
    $fund = DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $this->actingAs($this->user)->getJson("/api/za/retirement/funds/{$fund->id}/buckets")
        ->assertOk()
        ->assertJsonStructure(['data' => ['fund_holding_id', 'vested_minor', 'provident_vested_pre2021_minor', 'savings_minor', 'retirement_minor', 'total_minor']]);
});

it('splits a pre-2024-09-01 contribution into 100 percent vested', function () {
    $fund = DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/contributions', [
        'fund_holding_id' => $fund->id,
        'amount_minor' => 100000,
        'contribution_date' => '2024-08-01',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.split.vested_minor', 100000)
        ->assertJsonPath('data.split.savings_minor', 0)
        ->assertJsonPath('data.split.retirement_minor', 0)
        ->assertJsonPath('data.buckets.vested_minor', 100000);
});

it('splits a post-2024-09-01 contribution one third savings two thirds retirement', function () {
    $fund = DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/contributions', [
        'fund_holding_id' => $fund->id,
        'amount_minor' => 300000,
        'contribution_date' => '2024-10-01',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.split.vested_minor', 0)
        ->assertJsonPath('data.split.savings_minor', 100000)
        ->assertJsonPath('data.split.retirement_minor', 200000);
});

it('returns simulate response with tax delta and crosses_bracket flag', function () {
    $fund = DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/savings-pot/simulate', [
        'fund_holding_id' => $fund->id,
        'amount_minor' => 250000,
        'current_annual_income_minor' => 24000000,
        'age' => 40,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['tax_delta_minor', 'net_received_minor', 'marginal_rate', 'crosses_bracket']]);
});

it('returns 422 when savings-pot withdrawal is below R2000 minimum', function () {
    $fund = DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $this->actingAs($this->user)->postJson('/api/za/retirement/savings-pot/simulate', [
        'fund_holding_id' => $fund->id,
        'amount_minor' => 100000,
        'current_annual_income_minor' => 24000000,
        'age' => 40,
        'tax_year' => '2026/27',
    ])->assertStatus(422);
});

it('withdraws from savings pot and decrements savings bucket', function () {
    $fund = DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $this->actingAs($this->user)->postJson('/api/za/retirement/contributions', [
        'fund_holding_id' => $fund->id,
        'amount_minor' => 3000000,
        'contribution_date' => '2024-10-01',
    ])->assertCreated();

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/savings-pot/withdraw', [
        'fund_holding_id' => $fund->id,
        'amount_minor' => 500000,
        'current_annual_income_minor' => 24000000,
        'age' => 40,
        'tax_year' => '2026/27',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.withdrawal.gross_minor', 500000)
        ->assertJsonStructure(['data' => ['withdrawal' => ['gross_minor', 'tax_minor', 'net_minor'], 'buckets']]);

    $bucket = ZaRetirementFundBucket::where('user_id', $this->user->id)->where('fund_holding_id', $fund->id)->first();
    expect($bucket->savings_balance_minor)->toBe(500000);
});

it('calculates tax relief under Section 11F cap', function () {
    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/tax-relief/calculate', [
        'contribution_minor' => 50000000,
        'gross_income_minor' => 100000000,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['relief_amount_minor', 'relief_rate', 'net_cost_minor', 'method', 'tax_year']])
        ->assertJsonPath('data.method', 'section_11f');
});

it('quotes a living annuity with in-band drawdown', function () {
    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/annuities/living/quote', [
        'capital_minor' => 200000000,
        'drawdown_rate_bps' => 500,
        'age' => 65,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.kind', 'living')
        ->assertJsonPath('data.drawdown_rate_bps', 500)
        ->assertJsonStructure(['data' => ['annual_income_minor', 'monthly_income_minor', 'net_monthly_income_minor', 'marginal_rate']]);
});

it('returns 422 for living annuity drawdown outside 2.5-17.5 percent band', function () {
    $this->actingAs($this->user)->postJson('/api/za/retirement/annuities/living/quote', [
        'capital_minor' => 200000000,
        'drawdown_rate_bps' => 2000,
        'age' => 65,
        'tax_year' => '2026/27',
    ])->assertStatus(422);
});

it('quotes a life annuity with Section 10C exemption applied', function () {
    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/annuities/life/quote', [
        'annual_annuity_minor' => 6000000,
        'declared_section_10c_pool_minor' => 2000000,
        'age' => 65,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.kind', 'life')
        ->assertJsonPath('data.section_10c_exempt_minor', 2000000)
        ->assertJsonStructure(['data' => ['taxable_minor', 'section_10c_remaining_pool_minor', 'pool_exhausted']]);
});

it('apportions below R165k de minimis as full lump sum', function () {
    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/annuities/compulsory-apportion', [
        'vested_minor' => 10000000,
        'provident_vested_pre2021_minor' => 0,
        'retirement_minor' => 0,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.de_minimis_applied', true)
        ->assertJsonPath('data.pcls_minor', 10000000)
        ->assertJsonPath('data.compulsory_annuity_minor', 0);
});

it('prevents fund access for a different user', function () {
    $otherUser = User::factory()->create();
    $fund = DCPension::create([
        'user_id' => $otherUser->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $this->actingAs($this->user)->getJson("/api/za/retirement/funds/{$fund->id}/buckets")
        ->assertNotFound();
});
