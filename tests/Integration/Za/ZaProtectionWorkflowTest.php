<?php

declare(strict_types=1);

use App\Models\User;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(\Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['annual_employment_income' => 480_000]);
    Sanctum::actingAs($this->user);
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('updates the coverage-gap after creating a policy', function () {
    $baseline = $this->getJson('/api/za/protection/coverage-gap')->json('data');
    $lifeBefore = collect($baseline)->firstWhere('category', 'life');

    $this->postJson('/api/za/protection/policies', [
        'product_type' => 'life',
        'provider' => 'Discovery Life',
        'cover_amount_minor' => 5_000_000_00,
        'premium_amount_minor' => 1_500_00,
        'premium_frequency' => 'monthly',
        'start_date' => '2026-01-01',
    ])->assertCreated();

    $after = $this->getJson('/api/za/protection/coverage-gap')->json('data');
    $lifeAfter = collect($after)->firstWhere('category', 'life');

    expect($lifeAfter['existing_cover_minor'])->toBe($lifeBefore['existing_cover_minor'] + 5_000_000_00);
    expect($lifeAfter['shortfall_minor'])->toBeLessThan($lifeBefore['shortfall_minor']);
});

it('updates the coverage-gap after deleting a policy', function () {
    $policy = ZaProtectionPolicy::factory()->for($this->user)->life()->create([
        'cover_amount_minor' => 5_000_000_00,
    ]);

    $before = collect($this->getJson('/api/za/protection/coverage-gap')->json('data'))
        ->firstWhere('category', 'life');

    $this->deleteJson("/api/za/protection/policies/{$policy->id}")->assertOk();

    $after = collect($this->getJson('/api/za/protection/coverage-gap')->json('data'))
        ->firstWhere('category', 'life');

    expect($after['existing_cover_minor'])->toBe(0);
    expect($after['shortfall_minor'])->toBeGreaterThan($before['shortfall_minor']);
});
