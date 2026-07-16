<?php

declare(strict_types=1);

use Database\Seeders\JurisdictionSeeder;
use Fynla\Core\Jurisdiction\AssignPrimaryJurisdiction;
use Fynla\Core\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaJurisdictionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(JurisdictionSeeder::class);
    $this->seed(ZaJurisdictionSeeder::class);
});

it('backfills GB for users with no jurisdiction and skips those who have one', function () {
    $bare = User::factory()->create();
    $bare->jurisdictions()->detach(); // guarantee row-less regardless of factory default
    $already = User::factory()->create();
    (new AssignPrimaryJurisdiction)->assign($already, 'ZA');

    $this->artisan('jurisdictions:backfill')->assertExitCode(0);

    expect($bare->fresh()->jurisdictions->pluck('code')->all())->toBe(['GB'])
        ->and($already->fresh()->jurisdictions->pluck('code')->all())->toBe(['ZA']);
});
