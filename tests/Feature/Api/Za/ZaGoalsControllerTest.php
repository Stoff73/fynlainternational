<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(ZaTaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('returns SA-appropriate goal defaults', function () {
    $response = $this->getJson('/api/za/goals/defaults');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'tax_year',
            'data' => [
                'bond' => ['deposit_pct', 'default_term_years'],
                'tuition' => ['public_annual_minor', 'private_annual_minor'],
                'severance_tax_free_threshold_minor',
            ],
        ]);

    expect($response->json('data.bond.default_term_years'))->toBeGreaterThan(0)
        ->and($response->json('data.tuition.private_annual_minor'))
        ->toBeGreaterThan($response->json('data.tuition.public_annual_minor'));
});

it('calculates the tax on a severance lump sum', function () {
    // R400k severance, no prior lump sums — within the R550k 0% band, so no tax.
    $response = $this->postJson('/api/za/goals/severance-benefit', [
        'severance_amount_minor' => 400_000_00,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['success', 'tax_year', 'data' => ['tax_due_minor', 'tax_free_portion_minor', 'taxable_portion_minor', 'net_received_minor', 'threshold_applied_minor']]);

    expect($response->json('data.tax_due_minor'))->toBe(0)
        ->and($response->json('data.net_received_minor'))->toBe(400_000_00);
});

it('taxes a large severance above the free threshold', function () {
    $response = $this->postJson('/api/za/goals/severance-benefit', [
        'severance_amount_minor' => 2_000_000_00,
    ]);

    $response->assertOk();
    expect($response->json('data.tax_due_minor'))->toBeGreaterThan(0)
        ->and($response->json('data.net_received_minor'))->toBeLessThan(2_000_000_00);
});

it('rejects an unauthenticated goals defaults request', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/za/goals/defaults')->assertUnauthorized();
});

it('validates severance inputs', function () {
    $this->postJson('/api/za/goals/severance-benefit', ['severance_amount_minor' => -1])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['severance_amount_minor']);
});
