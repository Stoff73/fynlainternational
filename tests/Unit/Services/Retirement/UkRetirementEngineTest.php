<?php

declare(strict_types=1);

use App\Services\Retirement\UkRetirementEngine;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Fynla\Core\Contracts\RetirementEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    app(TaxConfigService::class)->clearCache();
    $this->engine = app(UkRetirementEngine::class);
});

it('implements the RetirementEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(RetirementEngine::class);
});

it('returns the UK annual pension allowance from TaxConfigService', function () {
    expect($this->engine->getAnnualAllowance('2025/26'))->toBeGreaterThanOrEqual(6_000_000);
});

it('returns null lifetime allowance (LTA abolished from 2024/25)', function () {
    expect($this->engine->getLifetimeAllowance('2025/26'))->toBeNull();
});

it('returns state pension age for a 1970 male (SPA 67)', function () {
    expect($this->engine->getStatePensionAge('1970-06-15', 'male'))->toBe(67);
});

it('returns SPA 68 for 1978+ births', function () {
    expect($this->engine->getStatePensionAge('1980-01-01', 'female'))->toBe(68);
});

it('projects compound pension growth', function () {
    $r = $this->engine->projectPensionGrowth([
        'current_value' => 1_000_000,
        'annual_contribution' => 500_000,
        'growth_rate' => 0.05,
        'years' => 5,
    ]);

    expect($r['projected_value'])->toBeGreaterThan(1_000_000);
    expect($r['year_by_year'])->toHaveCount(5);
});
