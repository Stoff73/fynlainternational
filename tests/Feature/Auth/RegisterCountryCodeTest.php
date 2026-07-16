<?php

declare(strict_types=1);

use Fynla\Core\Models\PendingRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects registration without a valid country_code', function () {
    $this->postJson('/api/auth/register', [
        'first_name' => 'Thabo', 'surname' => 'Nkosi',
        'email' => 'thabo@example.com', 'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertStatus(422)->assertJsonValidationErrors(['country_code']);
});

it('rejects an unsupported country_code', function () {
    $this->postJson('/api/auth/register', [
        'first_name' => 'Thabo', 'surname' => 'Nkosi',
        'email' => 'thabo@example.com', 'password' => 'Password1!',
        'password_confirmation' => 'Password1!', 'country_code' => 'FR',
    ])->assertStatus(422)->assertJsonValidationErrors(['country_code']);
});

it('persists country_code on the pending registration', function () {
    $this->postJson('/api/auth/register', [
        'first_name' => 'Thabo', 'surname' => 'Nkosi',
        'email' => 'thabo@example.com', 'password' => 'Password1!',
        'password_confirmation' => 'Password1!', 'country_code' => 'ZA',
    ])->assertStatus(201);

    expect(PendingRegistration::where('email', 'thabo@example.com')->first()->country_code)->toBe('ZA');
});
