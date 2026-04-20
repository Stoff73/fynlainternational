<?php

declare(strict_types=1);

use App\Models\User;
use Fynla\Packs\Za\Models\ZaProtectionBeneficiary;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Mirrors WS 1.4d ZaRetirementControllerTest pattern.
    // pack.enabled:za middleware reads FYNLA_ACTIVE_PACKS env var,
    // NOT user_jurisdictions rows. ZaTaxConfigurationSeeder internally
    // chains ZaJurisdictionSeeder, so no separate call needed.
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(\Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('lists protection policies for the authenticated user', function () {
    ZaProtectionPolicy::factory()->for($this->user)->count(3)->create();
    ZaProtectionPolicy::factory()->count(2)->create(); // other users' policies

    $response = $this->getJson('/api/za/protection/policies');

    $response->assertOk()->assertJsonPath('success', true);
    expect($response->json('data'))->toHaveCount(3);
});

it('creates a life policy with a spouse beneficiary', function () {
    $response = $this->postJson('/api/za/protection/policies', [
        'product_type' => 'life',
        'provider' => 'Discovery Life',
        'cover_amount_minor' => 5_000_000_00,
        'premium_amount_minor' => 1_500_00,
        'premium_frequency' => 'monthly',
        'start_date' => '2026-01-01',
        'beneficiaries' => [
            ['beneficiary_type' => 'spouse', 'name' => 'Test Spouse', 'allocation_percentage' => 100],
        ],
    ]);

    $response->assertCreated()->assertJsonPath('success', true);
    expect(ZaProtectionPolicy::count())->toBe(1);
    expect(ZaProtectionBeneficiary::count())->toBe(1);
});

it('rejects a dread policy without severity_tier', function () {
    $response = $this->postJson('/api/za/protection/policies', [
        'product_type' => 'dread',
        'provider' => 'Liberty',
        'cover_amount_minor' => 1_000_000_00,
        'premium_amount_minor' => 800_00,
        'premium_frequency' => 'monthly',
        'start_date' => '2026-01-01',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['severity_tier']);
});

it('requires waiting_period_months and benefit_term_months for idisability_income', function () {
    $response = $this->postJson('/api/za/protection/policies', [
        'product_type' => 'idisability_income',
        'provider' => 'Momentum',
        'cover_amount_minor' => 30_000_00,
        'premium_amount_minor' => 400_00,
        'premium_frequency' => 'monthly',
        'start_date' => '2026-01-01',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['waiting_period_months', 'benefit_term_months']);
});

it('shows a policy belonging to the user', function () {
    $policy = ZaProtectionPolicy::factory()->for($this->user)->life()->create();

    $response = $this->getJson("/api/za/protection/policies/{$policy->id}");

    $response->assertOk()->assertJsonPath('data.id', $policy->id);
});

it('returns 404 for a policy belonging to another user', function () {
    $policy = ZaProtectionPolicy::factory()->create();

    $response = $this->getJson("/api/za/protection/policies/{$policy->id}");

    $response->assertStatus(404);
});

it('updates a policy', function () {
    $policy = ZaProtectionPolicy::factory()->for($this->user)->life()->create([
        'cover_amount_minor' => 1_000_000_00,
    ]);

    $response = $this->putJson("/api/za/protection/policies/{$policy->id}", [
        'cover_amount_minor' => 6_000_000_00,
    ]);

    $response->assertOk();
    expect($policy->fresh()->cover_amount_minor)->toBe(6_000_000_00);
});

it('soft-deletes a policy', function () {
    $policy = ZaProtectionPolicy::factory()->for($this->user)->life()->create();

    $response = $this->deleteJson("/api/za/protection/policies/{$policy->id}");

    $response->assertOk();
    expect(ZaProtectionPolicy::find($policy->id))->toBeNull();
    expect(ZaProtectionPolicy::withTrashed()->find($policy->id))->not->toBeNull();
});

it('passes through policy types from the engine', function () {
    $response = $this->getJson('/api/za/protection/policy-types');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(6);
    expect(collect($response->json('data'))->pluck('code')->all())->toContain(
        'life', 'whole_of_life', 'dread', 'idisability_lump', 'idisability_income', 'funeral',
    );
});

it('passes through tax-treatment for a policy type', function () {
    $response = $this->getJson('/api/za/protection/tax-treatment/life');

    $response->assertOk();
    expect($response->json('data.premiums_deductible'))->toBe(false);
    expect($response->json('data.payout_taxable'))->toBe(false);
});

it('computes coverage-gap happy path with policies and user context', function () {
    $this->user->update(['annual_employment_income' => 480_000]);

    ZaProtectionPolicy::factory()->for($this->user)->life()->create([
        'cover_amount_minor' => 2_000_000_00,
    ]);

    $response = $this->getJson('/api/za/protection/coverage-gap');

    $response->assertOk();
    $data = collect($response->json('data'));
    $life = $data->firstWhere('category', 'life');
    expect($life['existing_cover_minor'])->toBe(2_000_000_00);
    expect($life['shortfall_minor'])->toBeGreaterThan(0);
    expect($life['missing_inputs'])->toBe([]);
});

it('flags missing_inputs in coverage-gap when user has no income data', function () {
    $response = $this->getJson('/api/za/protection/coverage-gap');

    $response->assertOk();
    $data = collect($response->json('data'));
    $life = $data->firstWhere('category', 'life');
    expect($life['missing_inputs'])->toContain('annual_income');
});

it('summarises the dashboard payload', function () {
    ZaProtectionPolicy::factory()->for($this->user)->life()->create([
        'premium_amount_minor' => 1_500_00,
        'premium_frequency' => 'monthly',
    ]);
    ZaProtectionPolicy::factory()->for($this->user)->funeral()->create([
        'premium_amount_minor' => 150_00,
        'premium_frequency' => 'monthly',
    ]);

    $response = $this->getJson('/api/za/protection/dashboard');

    $response->assertOk()->assertJsonPath('success', true);
    expect($response->json('data.total_monthly_premium_minor'))->toBe(1_650_00);
    expect($response->json('data.policies_by_type'))->toBeArray();
});

it('allows protection endpoints access when ZA pack is enabled', function () {
    // Per-user ZA jurisdiction enforcement lands in WS-D (see TODO(WS-D) in
    // routes/api.php). In this phase, pack.enabled:za middleware checks pack
    // registration (via PackRegistry), not user entitlements. This test verifies
    // that when the pack IS registered (as set up in beforeEach), the endpoint
    // is accessible to any authenticated user.
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/za/protection/dashboard');

    $response->assertOk();
});

it('blocks writes from preview users', function () {
    $this->user->is_preview_user = true;
    $this->user->save();

    $response = $this->postJson('/api/za/protection/policies', [
        'product_type' => 'life',
        'provider' => 'Discovery Life',
        'cover_amount_minor' => 5_000_000_00,
        'premium_amount_minor' => 1_500_00,
        'premium_frequency' => 'monthly',
        'start_date' => '2026-01-01',
    ]);

    // PreviewWriteInterceptor returns 200 with is_preview=true, not 403 or a row creation.
    $response->assertOk();
    expect(ZaProtectionPolicy::count())->toBe(0);
});

it('returns joint-owner policies in the list', function () {
    $other = User::factory()->create();
    ZaProtectionPolicy::factory()
        ->for($other, 'user')
        ->create(['joint_owner_id' => $this->user->id]);

    $response = $this->getJson('/api/za/protection/policies');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('cascades beneficiary delete when the policy is hard-deleted via force delete', function () {
    $policy = ZaProtectionPolicy::factory()->for($this->user)->life()->create();
    ZaProtectionBeneficiary::factory()->for($policy, 'policy')->count(2)->create();

    $policy->forceDelete();

    expect(ZaProtectionBeneficiary::where('policy_id', $policy->id)->count())->toBe(0);
});

it('refuses to update a policy belonging to another user', function () {
    $policy = ZaProtectionPolicy::factory()->create();

    $response = $this->putJson("/api/za/protection/policies/{$policy->id}", [
        'cover_amount_minor' => 999_999_99,
    ]);

    $response->assertStatus(404);
});
