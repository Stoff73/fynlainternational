<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Database\Seeders\TaxConfigurationSeeder;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The savings index path resolves the active tax year via TaxConfigService;
    // RefreshDatabase wipes it, so seed it (mirrors the Feature-suite default).
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Test Gauntlet G-4-c — Horizontal-privilege morph escalation.
 *
 * The pack relocation rewrote the polymorphic tokenable_type from
 * App\Models\User to Fynla\Core\Models\User (backfilled by migration, aliased
 * by class_alias). The real threat is NOT class injection — it is a token
 * whose tokenable is steered at another user. These tests prove that:
 *   1. a steered token authenticates AS the pointed-at user (expected), and
 *   2. resource-ownership checks still reject access to the original owner's
 *      records — the class_alias is one-directional and is NOT the deny-list;
 *      the per-query user_id scoping is.
 */
it('a token steered at another user authenticates as that user but cannot reach the original owner resources', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Account belongs to A.
    $accountA = SavingsAccount::factory()->create([
        'user_id' => $userA->id,
        'current_balance' => 5000,
    ]);

    // Forge a token that BELONGS to A's row but points its tokenable at B.
    $plain = $userA->createToken('steered')->plainTextToken;
    $tokenId = (int) explode('|', $plain)[0];
    PersonalAccessToken::whereKey($tokenId)->update([
        'tokenable_type' => User::class,
        'tokenable_id' => $userB->id,
    ]);

    $headers = ['Authorization' => "Bearer {$plain}", 'Accept' => 'application/json'];

    // The token IS functional — it authenticates as B (whoever tokenable points at).
    $me = $this->getJson('/api/gb/savings', $headers);
    $me->assertOk();

    // But B must NOT be able to read A's account — ownership scoping rejects it.
    $this->getJson("/api/gb/savings/accounts/{$accountA->id}", $headers)
        ->assertNotFound();
});

it('fails closed on a nonsense tokenable_type and leaks no data', function () {
    $user = User::factory()->create();
    $plain = $user->createToken('bad-morph')->plainTextToken;
    $tokenId = (int) explode('|', $plain)[0];

    // Point the morph at a non-authenticatable class. Setting this requires
    // DB write access (attacker has already won), so this is a robustness
    // probe, not an exploit path — the v1 plan marks it explicitly secondary.
    PersonalAccessToken::whereKey($tokenId)->update([
        'tokenable_type' => 'App\\Console\\Commands\\Nonexistent',
    ]);

    $response = $this->getJson('/api/gb/savings', [
        'Authorization' => "Bearer {$plain}",
        'Accept' => 'application/json',
    ]);

    // The load-bearing invariant: it never AUTHENTICATES (never 200), so no
    // account data is returned. It currently 500s during morph resolution
    // rather than the ideal 401 — logged as a low-severity robustness gap
    // (G-4-c-i) in the triage backlog; not exploitable without DB write.
    expect($response->status())->not->toBe(200);
    $response->assertJsonMissingPath('data');
});

it('resolves the legacy App\\Models\\User morph string to the core User via class_alias', function () {
    $user = User::factory()->create();
    $plain = $user->createToken('legacy-morph')->plainTextToken;
    $tokenId = (int) explode('|', $plain)[0];

    // Replicate a pre-relocation token row that the backfill migration missed.
    PersonalAccessToken::whereKey($tokenId)->update([
        'tokenable_type' => 'App\\Models\\User',
    ]);

    $this->getJson('/api/gb/savings', [
        'Authorization' => "Bearer {$plain}",
        'Accept' => 'application/json',
    ])->assertOk();
});
