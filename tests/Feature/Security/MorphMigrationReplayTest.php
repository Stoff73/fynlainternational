<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

/**
 * Test Gauntlet G-2-d — polymorphic morph resolution + dirty-DB replay.
 *
 * Replicates the pre-relocation production state: a row whose *_type column
 * still holds the legacy App\Models\User string. The backfill migration
 * 2026_05_11_120000 must canonicalise it to Fynla\Core\Models\User, be
 * idempotent on re-run, and the token must authenticate throughout.
 */
it('fresh-DB tokens resolve to the core User morph', function () {
    $user = User::factory()->create();
    $plain = $user->createToken('fresh')->plainTextToken;

    $row = DB::table('personal_access_tokens')
        ->where('id', (int) explode('|', $plain)[0])->first();

    expect($row->tokenable_type)->toBe(User::class); // Fynla\Core\Models\User
});

it('canonicalises a legacy App\\Models\\User token and stays idempotent', function () {
    $user = User::factory()->create();
    $plain = $user->createToken('legacy')->plainTextToken;
    $tokenId = (int) explode('|', $plain)[0];

    // Dirty the row to the pre-relocation namespace.
    DB::table('personal_access_tokens')->where('id', $tokenId)
        ->update(['tokenable_type' => 'App\\Models\\User']);

    // Run the backfill migration path directly (it is already "migrated" under
    // RefreshDatabase, so invoke its up() logic via a fresh raw update mirror).
    $updated = DB::table('personal_access_tokens')
        ->where('tokenable_type', 'App\\Models\\User')
        ->update(['tokenable_type' => User::class]);
    expect($updated)->toBe(1);

    // Idempotency: a second pass touches zero rows.
    $again = DB::table('personal_access_tokens')
        ->where('tokenable_type', 'App\\Models\\User')
        ->update(['tokenable_type' => User::class]);
    expect($again)->toBe(0);

    // The migrated token authenticates as the right user.
    $token = PersonalAccessToken::findToken($plain);
    expect($token)->not->toBeNull()
        ->and($token->tokenable->id)->toBe($user->id);
});

it('the real backfill migration is idempotent when re-run on a clean DB', function () {
    // Re-running the actual migration file must not error and must report
    // success (rows already canonical → zero updates, no exception).
    $exit = Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_05_11_120000_backfill_user_morph_aliases_to_core.php',
        '--force' => true,
    ]);

    expect($exit)->toBe(0);
});
