<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Models\ZaDonation;
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

it('records a donation and returns the donations-tax position', function () {
    // R150k donation this year — R100k annual exemption, R50k taxable at 20%.
    $response = $this->postJson('/api/za/estate/donations', [
        'amount_minor' => 150_000_00,
        'donation_date' => now()->format('Y-m-d'),
        'recipient' => 'Nephew',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['id', 'amount_minor', 'donation_date'], 'donations_tax' => ['tax_due_minor', 'annual_exemption_used_minor', 'this_year_minor']]);

    expect($response->json('donations_tax.this_year_minor'))->toBe(150_000_00)
        ->and($response->json('donations_tax.annual_exemption_used_minor'))->toBe(100_000_00)
        ->and($response->json('donations_tax.tax_due_minor'))->toBe(10_000_00); // 20% of R50k
});

it('lists only the caller donations and computes their cumulative tax', function () {
    $this->postJson('/api/za/estate/donations', ['amount_minor' => 50_000_00, 'donation_date' => now()->format('Y-m-d')])->assertCreated();
    $this->postJson('/api/za/estate/donations', ['amount_minor' => 30_000_00, 'donation_date' => now()->format('Y-m-d')])->assertCreated();

    $this->getJson('/api/za/estate/donations')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // R80k total, under the R100k exemption → no tax due.
    $this->getJson('/api/za/estate/donations-tax')
        ->assertOk()
        ->assertJsonPath('data.tax_due_minor', 0)
        ->assertJsonPath('data.this_year_minor', 80_000_00);
});

it('does not let a user delete another user donation', function () {
    $other = User::factory()->create();
    $donation = ZaDonation::create([
        'user_id' => $other->id,
        'amount_minor' => 20_000_00,
        'amount_ccy' => 'ZAR',
        'donation_date' => now()->format('Y-m-d'),
    ]);

    $this->deleteJson("/api/za/estate/donations/{$donation->id}")
        ->assertNotFound();

    expect(ZaDonation::find($donation->id))->not->toBeNull();
});

it('exempt donations are excluded from the donations-tax aggregation', function () {
    $this->postJson('/api/za/estate/donations', ['amount_minor' => 500_000_00, 'donation_date' => now()->format('Y-m-d'), 'is_exempt' => true])->assertCreated();

    $this->getJson('/api/za/estate/donations-tax')
        ->assertOk()
        ->assertJsonPath('data.this_year_minor', 0)
        ->assertJsonPath('data.tax_due_minor', 0);
});
