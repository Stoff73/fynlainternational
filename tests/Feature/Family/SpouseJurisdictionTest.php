<?php

declare(strict_types=1);

use Database\Seeders\JurisdictionSeeder;
use Fynla\Core\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaJurisdictionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(JurisdictionSeeder::class);
    $this->seed(ZaJurisdictionSeeder::class);
});

it('gives a newly-created spouse the creating user jurisdiction (WS1)', function () {
    $user = User::factory()->jurisdiction('ZA')->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/user/family-members', [
        'relationship' => 'spouse',
        'first_name' => 'Partner',
        'last_name' => 'Person',
        'email' => 'spouse@example.com',
        'date_of_birth' => '1985-01-01',
    ])->assertSuccessful();

    $spouse = User::where('email', 'spouse@example.com')->first();

    expect($spouse)->not->toBeNull()
        ->and($spouse->jurisdictions->pluck('code')->all())->toBe(['ZA']);
});
