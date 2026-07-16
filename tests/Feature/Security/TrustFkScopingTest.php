<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Core\Rules\BelongsToCurrentUser;
use Fynla\Packs\Gb\Models\Estate\Trust;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

/**
 * E-24 — unscoped foreign-key IDOR (trust aggregation vector).
 *
 * StorePropertyRequest/Chattel/BusinessInterest/Savings/Investment validated
 * trust_id only as exists:trusts,id (unscoped). An attacker could point an asset
 * at a victim's trust; TrustAssetAggregatorService sums every asset by trust_id
 * (never by owner), so the attacker's asset silently inflated the victim's estate
 * valuation. Fix: BelongsToCurrentUser scopes the FK to a trust the caller owns.
 * Same class as the fixed S4-H1 (see GoalLinkedAccountScopingTest).
 */
it('rejects a trust_id the caller does not own', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $victimTrust = Trust::factory()->create(['user_id' => $owner->id]);

    Auth::login($attacker);

    $failed = false;
    (new BelongsToCurrentUser('trusts'))->validate(
        'trust_id',
        $victimTrust->id,
        function () use (&$failed) {
            $failed = true;
        }
    );

    expect($failed)->toBeTrue();
});

it('accepts a trust_id the caller owns', function () {
    $user = User::factory()->create();
    $trust = Trust::factory()->create(['user_id' => $user->id]);

    Auth::login($user);

    $failed = false;
    (new BelongsToCurrentUser('trusts'))->validate('trust_id', $trust->id, function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('passes a null value through (nullable FK)', function () {
    Auth::login(User::factory()->create());

    $failed = false;
    (new BelongsToCurrentUser('trusts'))->validate('trust_id', null, function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('accepts a row the caller jointly owns when a joint column is configured', function () {
    $owner = User::factory()->create();
    $jointOwner = User::factory()->create();
    $account = SavingsAccount::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $jointOwner->id,
    ]);

    Auth::login($jointOwner);

    $failed = false;
    (new BelongsToCurrentUser('savings_accounts', ['user_id', 'joint_owner_id']))
        ->validate('linked_account_id', $account->id, function () use (&$failed) {
            $failed = true;
        });

    expect($failed)->toBeFalse();
});
