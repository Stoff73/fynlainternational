<?php

declare(strict_types=1);

use App\Services\Investment\UkInvestmentEngine;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Fynla\Core\Contracts\InvestmentEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    app(TaxConfigService::class)->clearCache();
    $this->engine = app(UkInvestmentEngine::class);
});

it('implements the InvestmentEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(InvestmentEngine::class);
});

it('lists UK tax wrappers (ISA, GIA)', function () {
    $codes = array_column($this->engine->getTaxWrappers(), 'code');

    expect($codes)->toContain('isa')->toContain('gia');
});

it('returns ISA annual allowance from TaxConfigService', function () {
    $allowances = $this->engine->getAnnualAllowances('2025/26');

    expect($allowances['isa'])->toBeGreaterThanOrEqual(2_000_000);
});

it('returns empty asset allocation rules (UK has no Reg 28 analogue)', function () {
    expect($this->engine->getAssetAllocationRules())->toBe([]);
});
