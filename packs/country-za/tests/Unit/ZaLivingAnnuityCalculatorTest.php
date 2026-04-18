<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaLivingAnnuityCalculator;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const LIVING_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->calc = app(ZaLivingAnnuityCalculator::class);
});

it('computes gross and net drawdown for a 5% election on R2m capital', function () {
    $r = $this->calc->calculate(
        capitalMinor: 200_000_000,
        drawdownRateBps: 500,
        age: 65,
        taxYear: LIVING_TAX_YEAR,
    );

    expect($r['gross_annual_minor'])->toBe(10_000_000);
    expect($r['drawdown_rate_bps'])->toBe(500);
    expect($r['tax_due_minor'])->toBe(0);
    expect($r['net_annual_minor'])->toBe(10_000_000);
});

it('rejects drawdown below 2.5%', function () {
    expect(fn () => $this->calc->calculate(200_000_000, 200, 65, LIVING_TAX_YEAR))
        ->toThrow(InvalidArgumentException::class, 'drawdown');
});

it('rejects drawdown above 17.5%', function () {
    expect(fn () => $this->calc->calculate(200_000_000, 1800, 65, LIVING_TAX_YEAR))
        ->toThrow(InvalidArgumentException::class, 'drawdown');
});

it('accepts exact boundary values 2.5% and 17.5%', function () {
    $min = $this->calc->calculate(200_000_000, 250, 65, LIVING_TAX_YEAR);
    $max = $this->calc->calculate(200_000_000, 1750, 65, LIVING_TAX_YEAR);

    expect($min['gross_annual_minor'])->toBe(5_000_000);
    expect($max['gross_annual_minor'])->toBe(35_000_000);
});

it('composes marginal tax for a high-drawdown case', function () {
    $r = $this->calc->calculate(300_000_000, 1500, 65, LIVING_TAX_YEAR);

    expect($r['gross_annual_minor'])->toBe(45_000_000);
    expect($r['tax_due_minor'])->toBeGreaterThan(0);
    expect($r['net_annual_minor'])->toBe($r['gross_annual_minor'] - $r['tax_due_minor']);
});
