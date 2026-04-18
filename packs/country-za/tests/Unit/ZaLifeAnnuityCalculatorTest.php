<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaLifeAnnuityCalculator;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const LIFE_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->calc = app(ZaLifeAnnuityCalculator::class);
});

it('applies Section 10C exemption up to the non-deductible pool', function () {
    $r = $this->calc->calculate(
        annualAnnuityMinor: 10_000_000,
        section10cPoolMinor: 4_000_000,
        age: 65,
        taxYear: LIFE_TAX_YEAR,
    );

    expect($r['section_10c_exempt_minor'])->toBe(4_000_000);
    expect($r['taxable_minor'])->toBe(6_000_000);
    expect($r['section_10c_remaining_pool_minor'])->toBe(0);
    expect($r['pool_exhausted'])->toBeTrue();
});

it('consumes only the annuity amount when pool exceeds annual income', function () {
    $r = $this->calc->calculate(10_000_000, 50_000_000, 65, LIFE_TAX_YEAR);

    expect($r['section_10c_exempt_minor'])->toBe(10_000_000);
    expect($r['taxable_minor'])->toBe(0);
    expect($r['section_10c_remaining_pool_minor'])->toBe(40_000_000);
    expect($r['pool_exhausted'])->toBeFalse();
    expect($r['tax_due_minor'])->toBe(0);
});

it('returns zero exempt when pool is zero', function () {
    $r = $this->calc->calculate(10_000_000, 0, 40, LIFE_TAX_YEAR);

    expect($r['section_10c_exempt_minor'])->toBe(0);
    expect($r['taxable_minor'])->toBe(10_000_000);
});

it('rejects negative inputs', function () {
    expect(fn () => $this->calc->calculate(-1, 0, 40, LIFE_TAX_YEAR))
        ->toThrow(InvalidArgumentException::class);
});
