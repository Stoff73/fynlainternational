<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    // RefreshDatabase rolls back the global DatabaseSeeder migrations
    // including za_tax_configurations rows; re-seed so the pack's
    // getAnnualContributionCap etc. read real values.
    $this->seed(ZaTaxConfigurationSeeder::class);
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('requires authentication for /api/za/savings/dashboard', function () {
    $response = $this->getJson('/api/za/savings/dashboard');
    $response->assertStatus(401);
});

// Pack registration is provider-level (ZaPackServiceProvider::boot), not
// env-var-gated — so FYNLA_ACTIVE_PACKS doesn't disable a discovered pack.
// A pack-not-available scenario requires removing the provider, which is
// infeasible at test time. Defer coverage to when /api/{cc}/* lands in WS D
// and ActiveJurisdictionMiddleware handles row-based entitlement.

it('returns a TFSA dashboard snapshot for a ZA-active user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/za/savings/dashboard?tax_year=2026/27');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'tax_year',
                'tfsa' => [
                    'annual_cap_minor',
                    'lifetime_cap_minor',
                    'annual_used_minor',
                    'lifetime_used_minor',
                    'annual_remaining_minor',
                    'lifetime_remaining_minor',
                ],
                'contributions',
            ],
        ]);

    expect($response->json('data.tfsa.annual_cap_minor'))->toBe(4_600_000);
    expect($response->json('data.tfsa.lifetime_cap_minor'))->toBe(50_000_000);
    expect($response->json('data.tfsa.annual_remaining_minor'))->toBe(4_600_000);
});

it('lists the authenticated user TFSA contributions', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    app('pack.za.tfsa.tracker')->record(
        userId: $user->id,
        beneficiaryId: null,
        savingsAccountId: null,
        taxYear: '2026/27',
        amountMinor: 1_000_000,
        contributionDate: now()->toDateString(),
    );

    $response = $this->getJson('/api/za/savings/contributions?tax_year=2026/27');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
    expect($response->json('data.0.amount_minor'))->toBe(1_000_000);
});

it('stores a TFSA contribution and returns updated caps', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/za/savings/contributions', [
        'tax_year' => '2026/27',
        'amount_minor' => 500_000,
        'contribution_date' => '2026-04-15',
        'source_type' => 'contribution',
        'notes' => 'Monthly top-up',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'tax_year',
                'amount_minor',
                'penalty_minor',
                'excess_minor',
                'annual_remaining_minor',
                'lifetime_remaining_minor',
            ],
        ]);

    expect($response->json('data.amount_minor'))->toBe(500_000);
    expect($response->json('data.penalty_minor'))->toBe(0);
    expect($response->json('data.annual_remaining_minor'))->toBe(4_600_000 - 500_000);
});

it('flags over-contribution penalty when annual cap breached', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/za/savings/contributions', [
        'tax_year' => '2026/27',
        'amount_minor' => 5_000_000,
        'contribution_date' => '2026-04-15',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.excess_minor'))->toBe(400_000);
    expect($response->json('data.penalty_minor'))->toBe(160_000);
    expect($response->json('data.breached_cap'))->toBe('annual');
});

it('assesses emergency fund adequacy with SA weighting', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/za/savings/emergency-fund/assess', [
        'current_balance_minor' => 3_000_000,
        'essential_monthly_expenditure_minor' => 1_500_000,
        'income_stability' => 'stable',
        'household_income_earners' => 1,
        'uif_eligible' => false,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'status',
                'shortfall_minor',
                'months_covered',
                'target_months',
                'target_minor',
                'weighting_reason',
            ],
        ]);

    // Precedence per ZaSavingsEngine: volatile > single-earner > uif-ineligible.
    // Single earner (1) takes precedence over UIF-ineligible — 6 months target,
    // reason 'single_earner'. The UIF bump only applies to dual-earner stable.
    expect($response->json('data.target_months'))->toBe(6);
    expect($response->json('data.target_minor'))->toBe(1_500_000 * 6);
    expect($response->json('data.weighting_reason'))->toBe('single_earner');
    expect($response->json('data.status'))->toBe('shortfall');
    expect((float) $response->json('data.months_covered'))->toBe(2.0);
});

it('stores a ZA savings account with TFSA flag', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/za/savings/accounts', [
        'institution' => 'Investec',
        'account_name' => 'TFSA — Investec Cash',
        'account_type' => 'tfsa',
        'current_balance' => 12_500.50,
        'interest_rate' => 7.5,
        'is_tfsa' => true,
    ]);

    $response->assertStatus(201);
    expect($response->json('data.is_tfsa'))->toBeTrue();
    expect($response->json('data.institution'))->toBe('Investec');
    expect($response->json('data.country_code'))->toBe('ZA');

    $this->assertDatabaseHas('savings_accounts', [
        'user_id' => $user->id,
        'is_tfsa' => true,
        'country_code' => 'ZA',
    ]);
});

it('lists the authenticated user ZA savings accounts', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'institution' => 'Standard Bank',
        'is_tfsa' => false,
        'country_code' => 'ZA',
    ]);

    $response = $this->getJson('/api/za/savings/accounts');
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});
