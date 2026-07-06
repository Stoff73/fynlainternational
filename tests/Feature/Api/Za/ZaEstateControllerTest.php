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

it('returns an estate-duty summary from the supplied position', function () {
    // R10m gross, R1m liabilities, no spousal transfer — dutiable after the
    // R3.5m s4A abatement, so some estate duty is due.
    $response = $this->postJson('/api/za/estate/summary', [
        'gross_estate_minor' => 10_000_000_00,
        'liabilities_minor' => 1_000_000_00,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'tax_year',
            'data' => [
                'tax_due', 'net_estate', 'effective_rate',
                'exemptions_applied' => ['abatement', 'spousal_transfer', 'other_exempt'],
                'reliefs_applied' => ['portability_used'],
                'breakdown' => ['dutiable_estate', 'executor_fees', 'has_predeceased_spouse'],
            ],
        ]);

    expect($response->json('data.tax_due'))->toBeGreaterThan(0)
        ->and($response->json('data.net_estate'))->toBeLessThan(10_000_000_00);
});

it('applies the unlimited spousal transfer to zero out estate duty', function () {
    $response = $this->postJson('/api/za/estate/summary', [
        'gross_estate_minor' => 10_000_000_00,
        'spouse_transfer_minor' => 10_000_000_00,
    ]);

    $response->assertOk();
    expect($response->json('data.tax_due'))->toBe(0);
});

it('returns exemptions and reliefs reference data', function () {
    $response = $this->getJson('/api/za/estate/exemptions');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'tax_year',
            'data' => [
                'exemptions' => ['abatement', 'spousal_transfer', 'cgt_death_exclusion'],
                'reliefs' => ['abatement_portability', 'spousal_rollover_cgt'],
            ],
        ]);
});

it('calculates CGT on death from a deemed gain', function () {
    $response = $this->postJson('/api/za/estate/cgt-on-death', [
        'deemed_gain_minor' => 5_000_000_00,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['success', 'tax_year', 'data' => ['taxable_amount_minor', 'exclusion_applied_minor', 'included_minor', 'tax_due_minor']]);

    // R5m gain, R300k death exclusion applied.
    expect($response->json('data.exclusion_applied_minor'))->toBe(30_000_000);
});

it('rejects an unauthenticated estate summary request', function () {
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/za/estate/summary', ['gross_estate_minor' => 1_000_000_00])
        ->assertUnauthorized();
});

it('validates the estate summary inputs', function () {
    $this->postJson('/api/za/estate/summary', ['gross_estate_minor' => -5])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['gross_estate_minor']);
});
