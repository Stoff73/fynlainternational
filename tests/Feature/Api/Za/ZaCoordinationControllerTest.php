<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\DCPension;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Models\ZaExchangeControlEntry;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Fynla\Packs\Za\Models\ZaTfsaContribution;
use Laravel\Sanctum\Sanctum;

function zaFund(int $userId): int
{
    return DCPension::create([
        'user_id' => $userId,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ])->id;
}

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(ZaTaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('returns an empty position summary for a user with no SA holdings', function () {
    $this->getJson('/api/za/coordination/summary')
        ->assertOk()
        ->assertJsonPath('data.total_minor', 0)
        ->assertJsonStructure(['data' => ['retirement_minor', 'tax_free_savings_minor', 'offshore_transferred_minor', 'total_minor', 'buckets']]);
});

it('aggregates the user SA position across modules', function () {
    ZaRetirementFundBucket::create([
        'user_id' => $this->user->id,
        'fund_holding_id' => zaFund($this->user->id),
        'vested_balance_minor' => 500_000_00,
        'provident_vested_pre2021_balance_minor' => 0,
        'savings_balance_minor' => 100_000_00,
        'retirement_balance_minor' => 400_000_00,
        'balance_ccy' => 'ZAR',
    ]);
    ZaTfsaContribution::create([
        'user_id' => $this->user->id,
        'tax_year' => '2026/27',
        'amount_minor' => 36_000_00,
        'amount_ccy' => 'ZAR',
        'contribution_date' => now()->format('Y-m-d'),
    ]);
    ZaExchangeControlEntry::create([
        'user_id' => $this->user->id,
        'allowance_type' => 'sda',
        'amount_minor' => 200_000_00,
        'amount_ccy' => 'ZAR',
        'calendar_year' => (int) now()->format('Y'),
        'transfer_date' => now()->format('Y-m-d'),
    ]);

    $response = $this->getJson('/api/za/coordination/summary');

    $response->assertOk()
        ->assertJsonPath('data.retirement_minor', 1_000_000_00)   // 500k+0+100k+400k
        ->assertJsonPath('data.tax_free_savings_minor', 36_000_00)
        ->assertJsonPath('data.offshore_transferred_minor', 200_000_00)
        ->assertJsonPath('data.total_minor', 1_236_000_00);

    expect($response->json('data.buckets'))->toHaveCount(3);
});

it('does not aggregate another user holdings', function () {
    $other = User::factory()->create();
    ZaRetirementFundBucket::create([
        'user_id' => $other->id,
        'fund_holding_id' => zaFund($other->id),
        'vested_balance_minor' => 999_000_00,
        'provident_vested_pre2021_balance_minor' => 0,
        'savings_balance_minor' => 0,
        'retirement_balance_minor' => 0,
        'balance_ccy' => 'ZAR',
    ]);

    $this->getJson('/api/za/coordination/summary')
        ->assertOk()
        ->assertJsonPath('data.retirement_minor', 0);
});

it('rejects an unauthenticated coordination request', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/za/coordination/summary')->assertUnauthorized();
});
