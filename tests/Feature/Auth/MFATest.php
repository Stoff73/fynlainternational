<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'mfa-test@example.com',
        'password' => bcrypt('password123'),
        'is_preview_user' => true,
    ]);
});

describe('MFA Status', function () {
    it('returns MFA disabled status for user without MFA', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/auth/mfa/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'mfa_enabled' => false,
                ],
            ]);
    });

    it('returns MFA enabled status for user with MFA', function () {
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('TESTSECRET123456'),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/auth/mfa/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'mfa_enabled' => true,
                ],
            ]);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/auth/mfa/status');

        $response->assertStatus(401);
    });
});

describe('MFA Setup', function () {
    it('generates QR code and secret', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/mfa/setup');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'qr_code',
                    'secret',
                ],
            ]);

        expect($response->json('data.secret'))->not()->toBeNull();
        expect($response->json('data.qr_code'))->toContain('data:image');
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/auth/mfa/setup');

        $response->assertStatus(401);
    });
});

describe('MFA Disable', function () {
    it('disables MFA when valid password AND recovery code are provided', function () {
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('TESTSECRET123456'),
            'mfa_recovery_codes' => [Hash::make('VALIDRECOVERY1')],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/mfa/disable', [
                'password' => 'password123',
                'code' => 'VALIDRECOVERY1',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->user->refresh();
        expect($this->user->mfa_enabled)->toBeFalse();
    });

    it('rejects disable with password alone (G-4-b H-2: MFA challenge required)', function () {
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('TESTSECRET123456'),
            'mfa_recovery_codes' => [Hash::make('VALIDRECOVERY1')],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/mfa/disable', [
                'password' => 'password123',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['code']);

        $this->user->refresh();
        expect($this->user->mfa_enabled)->toBeTrue();
    });

    it('rejects disable with wrong code', function () {
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('TESTSECRET123456'),
            'mfa_recovery_codes' => [Hash::make('VALIDRECOVERY1')],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/mfa/disable', [
                'password' => 'password123',
                'code' => 'WRONG-CODE',
            ]);

        $response->assertStatus(401);

        $this->user->refresh();
        expect($this->user->mfa_enabled)->toBeTrue();
    });

    it('requires password field', function () {
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('TESTSECRET123456'),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/mfa/disable', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/auth/mfa/disable', [
            'password' => 'password123',
            'code' => 'whatever',
        ]);

        $response->assertStatus(401);
    });
});

describe('MFA Recovery Codes', function () {
    it('regenerates recovery codes when valid password AND code are provided', function () {
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('TESTSECRET123456'),
            'mfa_recovery_codes' => [Hash::make('VALIDRECOVERY1')],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/mfa/recovery-codes', [
                'password' => 'password123',
                'code' => 'VALIDRECOVERY1',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'recovery_codes',
                ],
            ]);

        expect($response->json('data.recovery_codes'))->toHaveCount(10);
    });

    it('rejects regenerate with password alone (G-4-b H-3: MFA challenge required)', function () {
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('TESTSECRET123456'),
            'mfa_recovery_codes' => [Hash::make('VALIDRECOVERY1')],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/mfa/recovery-codes', [
                'password' => 'password123',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['code']);
    });

    it('rejects regenerate with wrong code', function () {
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('TESTSECRET123456'),
            'mfa_recovery_codes' => [Hash::make('VALIDRECOVERY1')],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/mfa/recovery-codes', [
                'password' => 'password123',
                'code' => 'WRONG-CODE',
            ]);

        $response->assertStatus(401);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/auth/mfa/recovery-codes', [
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    });
});
