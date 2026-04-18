<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaSavingsPotWithdrawalSimulator;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const SIM_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->sim = app(ZaSavingsPotWithdrawalSimulator::class);
});

it('returns the marginal-tax delta on a withdrawal', function () {
    $r = $this->sim->simulate(
        withdrawalMinor: 5_000_000,
        currentYearIncomeMinor: 45_000_000,
        age: 40,
        taxYear: SIM_TAX_YEAR,
    );

    expect($r['tax_delta_minor'])->toBeGreaterThan(0);
    expect($r['net_received_minor'])->toBe(5_000_000 - $r['tax_delta_minor']);
    expect($r['marginal_rate'])->toBe(31.0);
});

it('rejects withdrawals below the R2,000 minimum', function () {
    expect(fn () => $this->sim->simulate(
        withdrawalMinor: 100_000,
        currentYearIncomeMinor: 45_000_000,
        age: 40,
        taxYear: SIM_TAX_YEAR,
    ))->toThrow(InvalidArgumentException::class, 'below R2000 minimum');
});

it('accepts the exact R2,000 minimum', function () {
    $r = $this->sim->simulate(
        withdrawalMinor: 200_000,
        currentYearIncomeMinor: 45_000_000,
        age: 40,
        taxYear: SIM_TAX_YEAR,
    );

    expect($r['tax_delta_minor'])->toBeGreaterThan(0);
});

it('rejects negative withdrawals', function () {
    expect(fn () => $this->sim->simulate(-100, 45_000_000, 40, SIM_TAX_YEAR))
        ->toThrow(InvalidArgumentException::class);
});

it('flags bracket-crossing — withdrawal pushes member into a higher marginal band', function () {
    $r = $this->sim->simulate(
        withdrawalMinor: 2_000_000,
        currentYearIncomeMinor: 24_000_000,
        age: 40,
        taxYear: SIM_TAX_YEAR,
    );

    expect($r['crosses_bracket'])->toBeTrue();
});

it('does NOT flag bracket-crossing when both before and after are in the same band', function () {
    $r = $this->sim->simulate(
        withdrawalMinor: 2_000_000,
        currentYearIncomeMinor: 30_000_000,
        age: 40,
        taxYear: SIM_TAX_YEAR,
    );

    expect($r['crosses_bracket'])->toBeFalse();
});
