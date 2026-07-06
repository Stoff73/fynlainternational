<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaPreviewUserSeeder;
use Fynla\Packs\Za\Models\ZaExchangeControlEntry;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Fynla\Packs\Za\Models\ZaTfsaContribution;

it('seeds one SA preview persona with ZA jurisdiction and cross-module data', function () {
    $this->seed(ZaPreviewUserSeeder::class);

    $user = User::where('preview_persona_id', 'sa_professional')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_preview_user)->toBeTrue()
        ->and($user->primaryJurisdictionCode())->toBe('ZA');

    expect(ZaRetirementFundBucket::where('user_id', $user->id)->count())->toBe(1)
        ->and(ZaTfsaContribution::where('user_id', $user->id)->count())->toBe(1)
        ->and(ZaExchangeControlEntry::where('user_id', $user->id)->count())->toBe(1)
        ->and(ZaProtectionPolicy::where('user_id', $user->id)->count())->toBe(1);
});

it('is idempotent — reseeding keeps a single persona and one data set', function () {
    $this->seed(ZaPreviewUserSeeder::class);
    $this->seed(ZaPreviewUserSeeder::class);

    expect(User::where('preview_persona_id', 'sa_professional')->count())->toBe(1);

    $user = User::where('preview_persona_id', 'sa_professional')->first();
    expect(ZaRetirementFundBucket::where('user_id', $user->id)->count())->toBe(1)
        ->and(ZaTfsaContribution::where('user_id', $user->id)->count())->toBe(1);
});
