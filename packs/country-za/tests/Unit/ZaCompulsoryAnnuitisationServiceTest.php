<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaCompulsoryAnnuitisationService;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ANNUIT_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->svc = app(ZaCompulsoryAnnuitisationService::class);
});

it('allows full commutation when commutable total is under the R165k de minimis', function () {
    $r = $this->svc->apportion(
        vestedMinor: 10_000_000,
        providentVestedPre2021Minor: 0,
        retirementMinor: 5_000_000,
        taxYear: ANNUIT_TAX_YEAR,
    );

    expect($r['pcls_minor'])->toBe(10_000_000);
    expect($r['compulsory_annuity_minor'])->toBe(5_000_000);
    expect($r['de_minimis_applied'])->toBeTrue();
});

it('splits 1/3 PCLS + 2/3 compulsory on vested, locks retirement bucket regardless', function () {
    $r = $this->svc->apportion(
        vestedMinor: 30_000_000,
        providentVestedPre2021Minor: 0,
        retirementMinor: 60_000_000,
        taxYear: ANNUIT_TAX_YEAR,
    );

    expect($r['pcls_minor'])->toBe(10_000_000);
    expect($r['compulsory_annuity_minor'])->toBe(80_000_000);
    expect($r['de_minimis_applied'])->toBeFalse();
});

it('adds provident-pre-2021 100% commutable portion to PCLS', function () {
    $r = $this->svc->apportion(
        vestedMinor: 30_000_000,
        providentVestedPre2021Minor: 20_000_000,
        retirementMinor: 30_000_000,
        taxYear: ANNUIT_TAX_YEAR,
    );

    expect($r['pcls_minor'])->toBe(30_000_000);
    expect($r['compulsory_annuity_minor'])->toBe(50_000_000);
});

it('honours retirement-bucket-never-commutes even when commutable subset is under de minimis', function () {
    $r = $this->svc->apportion(
        vestedMinor: 5_000_000,
        providentVestedPre2021Minor: 0,
        retirementMinor: 5_000_000,
        taxYear: ANNUIT_TAX_YEAR,
    );

    expect($r['pcls_minor'])->toBe(5_000_000);
    expect($r['compulsory_annuity_minor'])->toBe(5_000_000);
    expect($r['de_minimis_applied'])->toBeTrue();
});

it('returns zero when all buckets are zero', function () {
    $r = $this->svc->apportion(0, 0, 0, ANNUIT_TAX_YEAR);

    expect($r['pcls_minor'])->toBe(0);
    expect($r['compulsory_annuity_minor'])->toBe(0);
});
