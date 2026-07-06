<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(ZaTaxConfigurationSeeder::class);
    Sanctum::actingAs(User::factory()->create());
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('returns the FAIS and POPIA disclosures', function () {
    $response = $this->getJson('/api/za/compliance');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'fais' => ['act', 'heading', 'body', 'review_required'],
                'popia' => ['act', 'heading', 'body', 'rights', 'review_required'],
            ],
        ]);

    // Both disclosures are flagged as needing compliance sign-off before prod.
    expect($response->json('data.fais.review_required'))->toBeTrue()
        ->and($response->json('data.popia.review_required'))->toBeTrue()
        ->and($response->json('data.fais.act'))->toContain('FAIS')
        ->and($response->json('data.popia.rights'))->toContain('Lodge a complaint with the Information Regulator (South Africa)');
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/za/compliance')->assertUnauthorized();
});
