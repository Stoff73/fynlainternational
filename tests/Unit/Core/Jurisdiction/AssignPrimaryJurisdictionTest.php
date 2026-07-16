<?php

declare(strict_types=1);

use Database\Seeders\JurisdictionSeeder;
use Fynla\Core\Jurisdiction\AssignPrimaryJurisdiction;
use Fynla\Core\Models\User;
use Fynla\Core\Models\UserJurisdiction;
use Fynla\Packs\Za\Database\Seeders\ZaJurisdictionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(JurisdictionSeeder::class);
    $this->seed(ZaJurisdictionSeeder::class);
});

it('assigns a primary jurisdiction to a user', function () {
    $user = User::factory()->create();

    $row = (new AssignPrimaryJurisdiction)->assign($user, 'ZA');

    expect($row->is_primary)->toBeTrue()
        ->and($user->fresh()->jurisdictions->pluck('code')->all())->toBe(['ZA']);
});

it('is idempotent — a second call updates, never duplicates', function () {
    $user = User::factory()->create();
    $svc = new AssignPrimaryJurisdiction;

    $svc->assign($user, 'GB');
    $svc->assign($user, 'ZA');

    expect(UserJurisdiction::where('user_id', $user->id)->where('is_primary', true)->count())->toBe(1)
        ->and($user->fresh()->jurisdictions->pluck('code')->all())->toBe(['ZA']);
});

it('throws for an unseeded country code', function () {
    $user = User::factory()->create();

    expect(fn () => (new AssignPrimaryJurisdiction)->assign($user, 'FR'))
        ->toThrow(RuntimeException::class);
});
