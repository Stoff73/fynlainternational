<?php

declare(strict_types=1);

use Fynla\Core\Models\Role;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

    $adminRole = Role::findByName(Role::ROLE_ADMIN);

    $this->mfaAdmin = User::factory()->create([
        'first_name' => 'MFA',
        'surname' => 'Admin',
        'email' => 'mfa-admin@test.com',
        'role_id' => $adminRole->id,
        'is_admin' => true,
        'is_preview_user' => true,
        'mfa_enabled' => true,
        'mfa_secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    $this->nonMfaAdmin = User::factory()->create([
        'first_name' => 'NoMFA',
        'surname' => 'Admin',
        'email' => 'nomfa-admin@test.com',
        'role_id' => $adminRole->id,
        'is_admin' => true,
        'is_preview_user' => true,
        'mfa_enabled' => false,
    ]);
});

describe('G-4-b slice 3 — H-1: admin write endpoints require MFA-verified token', function () {

    // For each write endpoint we test three scenarios:
    //   (a) MFA-enabled admin with non-MFA token → 403
    //   (b) MFA-enabled admin with MFA-verified token → not 403
    //   (c) Admin without MFA enabled → not 403 (legacy admins still work)

    $writeRoutes = [
        ['POST',   '/api/admin/users',                         ['email' => 'new@test.com', 'first_name' => 'X', 'surname' => 'Y', 'password' => 'Password1!']],
        ['DELETE', '/api/admin/users/9999',                    []],
        ['POST',   '/api/admin/ai-provider',                   ['provider' => 'anthropic']],
        ['POST',   '/api/admin/backup/create',                 []],
        ['POST',   '/api/admin/backup/restore',                ['filename' => 'noop.sql']],
        ['DELETE', '/api/admin/backup/delete',                 ['filename' => 'noop.sql']],
        ['POST',   '/api/admin/discount-codes',                ['code' => 'TEST']],
        ['DELETE', '/api/admin/discount-codes/9999',           []],
        ['PATCH',  '/api/admin/discount-codes/9999/toggle',    []],
    ];

    foreach ($writeRoutes as [$method, $uri, $payload]) {
        it("returns 403 for {$method} {$uri} when admin has MFA enabled but token is not MFA-verified", function () use ($method, $uri, $payload) {
            Sanctum::actingAs($this->mfaAdmin, []);

            $response = $this->json($method, $uri, $payload);

            expect($response->status())->toBe(403);
            expect($response->json('message'))->toContain('MFA');
        });

        it("does NOT return 403 for {$method} {$uri} when admin has MFA enabled AND token is MFA-verified", function () use ($method, $uri, $payload) {
            Sanctum::actingAs($this->mfaAdmin, ['mfa_verified']);
            session(['mfa_verified' => true]);

            $response = $this->json($method, $uri, $payload);

            expect($response->status())->not->toBe(403);
        });

        it("does NOT return 403 for {$method} {$uri} when admin has MFA disabled (legacy)", function () use ($method, $uri, $payload) {
            Sanctum::actingAs($this->nonMfaAdmin, []);

            $response = $this->json($method, $uri, $payload);

            expect($response->status())->not->toBe(403);
        });
    }
});

describe('G-4-b slice 3 — H-1: admin READ endpoints are NOT MFA-gated', function () {

    // Read endpoints stay on the legacy group — MFA-enabled admin without an
    // MFA-verified token must still be able to view dashboards / lists.

    $readRoutes = [
        '/api/admin/dashboard',
        '/api/admin/roles',
        '/api/admin/users',
        '/api/admin/subscriptions/stats',
        '/api/admin/ai-provider',
        '/api/admin/ai-audit/users',
        '/api/admin/backup/list',
        '/api/admin/user-metrics/snapshot',
        '/api/admin/discount-codes',
    ];

    foreach ($readRoutes as $uri) {
        it("allows GET {$uri} for MFA-enabled admin with a non-MFA token", function () use ($uri) {
            Sanctum::actingAs($this->mfaAdmin, []);

            $response = $this->getJson($uri);

            expect($response->status())->not->toBe(403);
        });
    }
});

describe('G-4-b slice 3 — H-1: PUT /api/admin/users/{id} variant', function () {
    it('returns 403 when MFA-enabled admin updates a user without MFA-verified token', function () {
        Sanctum::actingAs($this->mfaAdmin, []);

        $target = User::factory()->create();

        $response = $this->putJson("/api/admin/users/{$target->id}", [
            'first_name' => 'Updated',
        ]);

        expect($response->status())->toBe(403);
    });

    it('does NOT return 403 when MFA-enabled admin updates a user with MFA-verified token', function () {
        Sanctum::actingAs($this->mfaAdmin, ['mfa_verified']);
            session(['mfa_verified' => true]);

        $target = User::factory()->create();

        $response = $this->putJson("/api/admin/users/{$target->id}", [
            'first_name' => 'Updated',
        ]);

        expect($response->status())->not->toBe(403);
    });
});

describe('G-4-b slice 3 — H-1: PUT /api/admin/discount-codes/{id} variant', function () {
    it('returns 403 when MFA-enabled admin edits a discount code without MFA-verified token', function () {
        Sanctum::actingAs($this->mfaAdmin, []);

        $response = $this->putJson('/api/admin/discount-codes/9999', [
            'description' => 'updated',
        ]);

        expect($response->status())->toBe(403);
    });
});
