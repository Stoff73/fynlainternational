<?php

declare(strict_types=1);

use Fynla\Core\Contracts\PackAssetRepository;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * WS2 end-to-end: a ZA user's assets must reach the core composite
 * (PackAssetRepository) — which merges every pack — exactly once, without the GB
 * repo also claiming the ZA-tagged rows (double-count), and a GB user's
 * aggregation must stay GB-only.
 */
it('surfaces a ZA-tagged asset once through the core composite', function () {
    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'country_code' => 'ZA',
        'account_name' => 'TFSA',
        'current_balance' => 50000,
    ]);

    $assets = app(PackAssetRepository::class)->userAccounts($user->id);
    $types = $assets->pluck('type');

    expect($types)->toContain('za.savings_account')
        ->and($types)->not->toContain('gb.savings_account')
        ->and($assets->where('type', 'za.savings_account'))->toHaveCount(1);
});

it('leaves a GB user composite GB-only', function () {
    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'country_code' => 'GB',
        'current_balance' => 1000,
    ]);

    $types = app(PackAssetRepository::class)->userAccounts($user->id)->pluck('type');

    expect($types)->toContain('gb.savings_account')
        ->and($types)->not->toContain('za.savings_account');
});
