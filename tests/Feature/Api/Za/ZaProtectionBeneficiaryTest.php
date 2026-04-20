<?php

declare(strict_types=1);

use App\Models\User;
use Fynla\Packs\Za\Models\ZaProtectionBeneficiary;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Mirrors Task 9 ZaProtectionControllerTest + WS 1.4d ZaRetirementControllerTest pattern.
    // NEVER use syncWithoutDetaching on user_jurisdictions (wrong model/column/pivot per PRD audit).
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(\Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->policy = ZaProtectionPolicy::factory()->for($this->user)->life()->create();
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('lists beneficiaries for a policy', function () {
    ZaProtectionBeneficiary::factory()->for($this->policy, 'policy')->count(2)->create(['allocation_percentage' => 50]);

    $response = $this->getJson("/api/za/protection/beneficiaries/{$this->policy->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('replaces the beneficiary set atomically', function () {
    ZaProtectionBeneficiary::factory()->for($this->policy, 'policy')->create(['allocation_percentage' => 100]);

    $response = $this->postJson("/api/za/protection/beneficiaries/{$this->policy->id}", [
        'beneficiaries' => [
            ['beneficiary_type' => 'spouse', 'name' => 'Spouse', 'allocation_percentage' => 50],
            ['beneficiary_type' => 'nominated_individual', 'name' => 'Child', 'allocation_percentage' => 50, 'id_number' => '9001015009087'],
        ],
    ]);

    $response->assertOk();
    expect($this->policy->fresh()->beneficiaries)->toHaveCount(2);
});

it('rejects beneficiaries that do not sum to 100', function () {
    $response = $this->postJson("/api/za/protection/beneficiaries/{$this->policy->id}", [
        'beneficiaries' => [
            ['beneficiary_type' => 'spouse', 'name' => 'Spouse', 'allocation_percentage' => 60],
            ['beneficiary_type' => 'nominated_individual', 'name' => 'Child', 'allocation_percentage' => 50, 'id_number' => '9001015009087'],
        ],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['beneficiaries']);
});

it('accepts an estate beneficiary with null name', function () {
    $response = $this->postJson("/api/za/protection/beneficiaries/{$this->policy->id}", [
        'beneficiaries' => [
            ['beneficiary_type' => 'estate', 'allocation_percentage' => 100],
        ],
    ]);

    $response->assertOk();
    expect($this->policy->fresh()->beneficiaries->first()->beneficiary_type)->toBe('estate');
});

it('requires a name for nominated_individual', function () {
    $response = $this->postJson("/api/za/protection/beneficiaries/{$this->policy->id}", [
        'beneficiaries' => [
            ['beneficiary_type' => 'nominated_individual', 'allocation_percentage' => 100, 'id_number' => '9001015009087'],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['beneficiaries.0.name']);
});

it('cascade-deletes beneficiaries on policy force-delete', function () {
    ZaProtectionBeneficiary::factory()->for($this->policy, 'policy')->count(3)->create(['allocation_percentage' => 33.33]);
    expect(ZaProtectionBeneficiary::where('policy_id', $this->policy->id)->count())->toBe(3);

    $this->policy->forceDelete();

    expect(ZaProtectionBeneficiary::where('policy_id', $this->policy->id)->count())->toBe(0);
});
