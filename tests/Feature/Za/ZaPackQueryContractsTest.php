<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\DCPension;
use Fynla\Packs\Za\Models\ZaDonation;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Fynla\Packs\Za\Query\ZaPackAssetResolver;
use Fynla\Packs\Za\Query\ZaPackEstateRepository;
use Fynla\Packs\Za\Query\ZaPackUserRelationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * WS2: the remaining three ZA query contracts (estate, resolver, relation
 * provider) were Null stubs. They now surface SA's real concepts — donations as
 * gifts, the retirement bucket as a resolvable asset, ZA relation-type mapping —
 * and honestly return empty for UK concepts SA lacks.
 */
it('surfaces donations via giftsForUser and empties SA-absent estate concepts', function () {
    $user = User::factory()->create();
    ZaDonation::create([
        'user_id' => $user->id,
        'amount_minor' => 500000,
        'amount_ccy' => 'ZAR',
        'donation_date' => '2026-06-01',
        'recipient' => 'Child',
    ]);

    $repo = new ZaPackEstateRepository();

    expect($repo->giftsForUser($user->id))->toHaveCount(1)
        ->and($repo->liabilitiesForUser($user->id))->toHaveCount(0)
        ->and($repo->trustsForUser($user->id))->toHaveCount(0)
        ->and($repo->lpasForUser($user->id))->toHaveCount(0)
        ->and($repo->estateAssetsForUser($user->id))->toHaveCount(0)
        ->and($repo->ihtProfileForUser($user->id))->toBeNull();
});

it('resolves the za.retirement_fund tag and returns null for unknown tags', function () {
    $user = User::factory()->create();
    $dc = DCPension::factory()->create(['user_id' => $user->id, 'country_code' => 'ZA']);
    $bucket = ZaRetirementFundBucket::create([
        'user_id' => $user->id,
        'fund_holding_id' => $dc->id,
        'vested_balance_minor' => 100000,
        'provident_vested_pre2021_balance_minor' => 0,
        'savings_balance_minor' => 0,
        'retirement_balance_minor' => 0,
        'balance_ccy' => 'ZAR',
    ]);

    $resolver = new ZaPackAssetResolver();

    expect($resolver->resolveAccount('za.retirement_fund', $bucket->id))->not->toBeNull()
        ->and($resolver->resolveAccount('za.does_not_exist', $bucket->id))->toBeNull();
});

it('maps ZA relation types to their models', function () {
    $user = User::factory()->create();
    ZaDonation::create([
        'user_id' => $user->id,
        'amount_minor' => 100,
        'amount_ccy' => 'ZAR',
        'donation_date' => '2026-06-01',
    ]);

    $provider = new ZaPackUserRelationProvider();

    expect($provider->modelClassFor('za.donation'))->toBe(ZaDonation::class)
        ->and($provider->modelClassFor('za.unknown'))->toBeNull()
        ->and($provider->userRelatedModels($user->id, 'za.donation'))->toHaveCount(1)
        ->and($provider->userRelatedModel($user->id, 'za.donation'))->not->toBeNull();
});
