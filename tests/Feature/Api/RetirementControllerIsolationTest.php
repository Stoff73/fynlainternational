<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->user = User::factory()->create();
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('excludes ZA-coded DC pensions from the UK retirement index', function () {
    DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'occupational',
        'scheme_type' => 'workplace',
        'provider' => 'UK Plc Ltd',
        'country_code' => 'GB',
    ]);

    DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray ZA Scheme',
        'country_code' => 'ZA',
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/retirement');

    $response->assertOk();
    $serialised = json_encode($response->json('data'));
    expect($serialised)->not->toContain('Allan Gray ZA Scheme');
    expect($serialised)->toContain('UK Plc Ltd');
});

it('includes NULL-country-code DC pensions in the UK retirement index (legacy rows)', function () {
    DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'occupational',
        'scheme_type' => 'workplace',
        'provider' => 'Legacy Provider',
        'country_code' => null,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/retirement');

    $response->assertOk();
    $body = json_encode($response->json('data'));
    expect($body)->toContain('Legacy Provider');
});
