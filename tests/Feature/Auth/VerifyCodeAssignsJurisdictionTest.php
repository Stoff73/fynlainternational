<?php

declare(strict_types=1);

use Database\Seeders\JurisdictionSeeder;
use Fynla\Core\Models\PendingRegistration;
use Fynla\Core\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaJurisdictionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(JurisdictionSeeder::class);
    $this->seed(ZaJurisdictionSeeder::class);
    $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
    $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
});

function registerAndVerify(string $email, string $countryCode): void
{
    $register = test()->postJson('/api/auth/register', [
        'first_name' => 'Test', 'surname' => 'User',
        'email' => $email, 'password' => 'Password1!',
        'password_confirmation' => 'Password1!', 'country_code' => $countryCode,
    ]);
    $register->assertStatus(201);

    $pendingId = $register->json('data.pending_id');
    $code = PendingRegistration::find($pendingId)->verification_code;

    test()->postJson('/api/auth/verify-code', [
        'type' => 'registration', 'pending_id' => $pendingId, 'code' => $code,
    ])->assertSuccessful();
}

it('assigns ZA when a user registers with South Africa and verifies', function () {
    registerAndVerify('thabo@example.com', 'ZA');

    $user = User::where('email', 'thabo@example.com')->first();
    expect($user->jurisdictions->pluck('code')->all())->toBe(['ZA']);
});

it('assigns GB when a user registers with the United Kingdom and verifies', function () {
    registerAndVerify('ella@example.com', 'GB');

    $user = User::where('email', 'ella@example.com')->first();
    expect($user->jurisdictions->pluck('code')->all())->toBe(['GB']);
});
