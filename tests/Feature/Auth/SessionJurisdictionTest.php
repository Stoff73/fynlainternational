<?php

declare(strict_types=1);

use Carbon\Carbon;
use Fynla\Core\Models\Jurisdiction;
use Fynla\Core\Models\User;
use Fynla\Core\Models\UserJurisdiction;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Ensure the GB jurisdiction seeded by Jurisdictions backfill is available.
    $this->gb = Jurisdiction::firstOrCreate(
        ['code' => 'GB'],
        ['name' => 'United Kingdom', 'currency' => 'GBP', 'locale' => 'en-GB', 'active' => true]
    );
});

it('session endpoint returns active_jurisdictions and cross_border for a GB-only user', function () {
    $user = User::factory()->create();
    UserJurisdiction::create([
        'user_id' => $user->id,
        'jurisdiction_id' => $this->gb->id,
        'is_primary' => true,
        'activated_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/auth/user');

    $response->assertOk()
        ->assertJsonPath('data.active_jurisdictions', ['gb'])
        ->assertJsonPath('data.primary_jurisdiction', 'gb')
        ->assertJsonPath('data.cross_border', false);
});

it('session endpoint flags cross_border for a user in two jurisdictions', function () {
    $za = Jurisdiction::firstOrCreate(
        ['code' => 'ZA'],
        ['name' => 'South Africa', 'currency' => 'ZAR', 'locale' => 'en-ZA', 'active' => true]
    );

    $user = User::factory()->create();
    UserJurisdiction::create([
        'user_id' => $user->id,
        'jurisdiction_id' => $this->gb->id,
        'is_primary' => true,
        'activated_at' => now(),
    ]);
    UserJurisdiction::create([
        'user_id' => $user->id,
        'jurisdiction_id' => $za->id,
        'is_primary' => false,
        'activated_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/auth/user');

    $response->assertOk()
        ->assertJsonPath('data.primary_jurisdiction', 'gb')
        ->assertJsonPath('data.cross_border', true);

    expect($response->json('data.active_jurisdictions'))
        ->toContain('gb')
        ->toContain('za');
});

it('session endpoint returns empty jurisdictions list for users without any assignment', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/auth/user');

    $response->assertOk()
        ->assertJsonPath('data.active_jurisdictions', [])
        ->assertJsonPath('data.primary_jurisdiction', null)
        ->assertJsonPath('data.cross_border', false);
});

it('session endpoint returns ZAR localisation and the SA tax year for a ZA-primary user', function () {
    $za = Jurisdiction::firstOrCreate(
        ['code' => 'ZA'],
        ['name' => 'South Africa', 'currency' => 'ZAR', 'locale' => 'en-ZA', 'active' => true]
    );

    // Date-independent SA tax year row spanning today (1 March – end Feb).
    $today = now();
    $startYear = $today->month >= 3 ? $today->year : $today->year - 1;
    $startsOn = sprintf('%d-03-01', $startYear);
    $endsOn = Carbon::create($startYear + 1, 3, 1)->subDay()->toDateString();
    $label = sprintf('%d/%s', $startYear, substr((string) ($startYear + 1), -2));

    DB::table('tax_years')->updateOrInsert(
        ['jurisdiction_id' => $za->id, 'starts_on' => $startsOn],
        [
            'label' => $label,
            'calendar_type' => 'tax_year',
            'ends_on' => $endsOn,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $user = User::factory()->create();
    UserJurisdiction::create([
        'user_id' => $user->id,
        'jurisdiction_id' => $za->id,
        'is_primary' => true,
        'activated_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.localisation.currency_code', 'ZAR')
        ->assertJsonPath('data.localisation.currency_symbol', 'R')
        ->assertJsonPath('data.localisation.locale', 'en_ZA')
        ->assertJsonPath('data.localisation.date_format', 'd M Y')
        ->assertJsonPath('data.tax_year.label', $label)
        ->assertJsonPath('data.tax_year.starts_on', $startsOn)
        ->assertJsonPath('data.tax_year.ends_on', $endsOn);
});

it('session endpoint returns GBP localisation and null tax_year for a GB-primary user', function () {
    $user = User::factory()->create();
    UserJurisdiction::create([
        'user_id' => $user->id,
        'jurisdiction_id' => $this->gb->id,
        'is_primary' => true,
        'activated_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.localisation.currency_code', 'GBP')
        ->assertJsonPath('data.localisation.currency_symbol', '£')
        ->assertJsonPath('data.localisation.locale', 'en_GB')
        ->assertJsonPath('data.localisation.date_format', 'd/m/Y')
        ->assertJsonPath('data.tax_year', null);
});

it('session endpoint returns GB-shaped localisation defaults for a user with no jurisdiction rows', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.localisation.currency_code', 'GBP')
        ->assertJsonPath('data.localisation.currency_symbol', '£')
        ->assertJsonPath('data.localisation.locale', 'en_GB')
        ->assertJsonPath('data.localisation.date_format', 'd/m/Y')
        ->assertJsonPath('data.tax_year', null);
});
