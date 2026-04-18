<?php

declare(strict_types=1);

use App\Services\Savings\UkSavingsEngine;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Fynla\Core\Contracts\SavingsEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    app(TaxConfigService::class)->clearCache();
    $this->engine = app(UkSavingsEngine::class);
});

it('implements the SavingsEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(SavingsEngine::class);
});

it('returns no lifetime cap for UK ISA', function () {
    expect($this->engine->getLifetimeContributionCap('2025/26'))->toBeNull();
});

it('returns the UK ISA annual cap from TaxConfigService', function () {
    // UK ISA annual allowance is currently £20,000 = 2_000_000 minor units (pence).
    // Value sourced from the TaxConfigService-backed seed, not a hardcoded constant.
    expect($this->engine->getAnnualContributionCap('2025/26'))
        ->toBeInt()
        ->toBeGreaterThanOrEqual(2_000_000);
});

it('stubs TFSA wrapper-penalty as zero-penalty for UK (ISA excess is taxed, not penalised)', function () {
    // Contribution of £25,000 when prior = £0 → £5,000 excess but no flat penalty.
    $r = $this->engine->calculateTaxFreeWrapperPenalty(
        contributionMinor: 2_500_000,
        annualPriorMinor: 0,
        lifetimePriorMinor: 0,
        taxYear: '2025/26',
    );

    expect($r['penalty_minor'])->toBe(0);
    expect($r['excess_minor'])->toBeGreaterThan(0);
    expect($r['breached_cap'])->toBe('annual');
});
