<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\DCPension;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Fynla\Packs\Za\Query\ZaPackAssetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * WS2: the ZA asset repository was a Null stub returning empty. It now surfaces
 * the SA user's real net-worth assets (savings/TFSA, investments, retirement) as
 * ZAR AssetSummary rows so they reach the core Net Worth / Dashboard aggregation.
 */
it('surfaces ZA savings and retirement as ZAR AssetSummary', function () {
    $user = User::factory()->create();

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'country_code' => 'ZA',
        'account_name' => 'My TFSA',
        'current_balance' => 50000,
    ]);

    $dc = DCPension::factory()->create(['user_id' => $user->id, 'country_code' => 'ZA']);
    ZaRetirementFundBucket::create([
        'user_id' => $user->id,
        'fund_holding_id' => $dc->id,
        'vested_balance_minor' => 100000,
        'provident_vested_pre2021_balance_minor' => 0,
        'savings_balance_minor' => 0,
        'retirement_balance_minor' => 0,
        'balance_ccy' => 'ZAR',
    ]);

    $assets = (new ZaPackAssetRepository)->userAccounts($user->id);

    expect($assets)->toHaveCount(2);

    $byType = $assets->keyBy('type');
    expect($byType['za.savings_account']->valueMinor)->toBe(5000000)
        ->and($byType['za.savings_account']->currency)->toBe('ZAR')
        ->and($byType['za.retirement_fund']->valueMinor)->toBe(100000)
        ->and($byType['za.retirement_fund']->currency)->toBe('ZAR');
});

it('does not surface GB (non-ZA) assets — country_code discrimination', function () {
    $user = User::factory()->create();

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'country_code' => 'GB',
        'current_balance' => 999,
    ]);

    expect((new ZaPackAssetRepository)->userAccounts($user->id))->toHaveCount(0);
});
