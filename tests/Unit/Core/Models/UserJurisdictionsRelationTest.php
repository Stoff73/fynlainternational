<?php

declare(strict_types=1);

use Fynla\Core\Models\Jurisdiction;
use Fynla\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('excludes soft-deactivated jurisdictions from the relation (WS1)', function () {
    $gb = Jurisdiction::create(['code' => 'GB', 'name' => 'United Kingdom', 'currency' => 'GBP', 'locale' => 'en-GB', 'active' => true]);
    $za = Jurisdiction::create(['code' => 'ZA', 'name' => 'South Africa', 'currency' => 'ZAR', 'locale' => 'en-ZA', 'active' => true]);
    $user = User::factory()->create();

    $user->jurisdictions()->attach($gb->id, ['is_primary' => true, 'activated_at' => now()]);
    $user->jurisdictions()->attach($za->id, ['is_primary' => false, 'activated_at' => now(), 'deactivated_at' => now()]);

    $codes = $user->fresh()->jurisdictions->pluck('code')->all();

    expect($codes)->toContain('GB')->not->toContain('ZA');
});
