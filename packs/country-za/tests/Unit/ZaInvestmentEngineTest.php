<?php

declare(strict_types=1);

use Fynla\Core\Contracts\InvestmentEngine;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Investment\ZaInvestmentEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ZA_INV_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->engine = app(ZaInvestmentEngine::class);
});

it('implements the InvestmentEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(InvestmentEngine::class);
});

describe('Tax wrappers', function () {
    it('lists the three SA wrappers', function () {
        $codes = array_column($this->engine->getTaxWrappers(), 'code');

        expect($codes)->toContain('tfsa', 'discretionary', 'endowment');
    });
});

describe('Annual allowances', function () {
    it('exposes the TFSA annual cap via the savings engine', function () {
        $allowances = $this->engine->getAnnualAllowances(ZA_INV_TAX_YEAR);

        expect($allowances['tfsa'])->toBe(4_600_000);
    });

    it('reports discretionary as unbounded', function () {
        $allowances = $this->engine->getAnnualAllowances(ZA_INV_TAX_YEAR);

        expect($allowances['discretionary'])->toBe(PHP_INT_MAX);
    });

    it('reports endowment as unbounded', function () {
        $allowances = $this->engine->getAnnualAllowances(ZA_INV_TAX_YEAR);

        expect($allowances['endowment'])->toBe(PHP_INT_MAX);
    });
});

describe('calculateInvestmentTax', function () {
    it('routes discretionary gains through ZaCgtCalculator', function () {
        $r = $this->engine->calculateInvestmentTax([
            'wrapper_code' => 'discretionary',
            'gains' => 5_000_000,
            'dividends' => 0,
            'interest' => 0,
            'tax_year' => ZA_INV_TAX_YEAR,
            'income_minor' => 40_000_000,
            'age' => 40,
        ]);

        expect($r['gains_tax'])->toBeGreaterThan(0);
        expect($r['dividend_tax'])->toBe(0);
        expect($r['interest_tax'])->toBe(0);
        expect($r['total_tax'])->toBe($r['gains_tax']);
        expect($r['breakdown']['wrapper_code'])->toBe('discretionary');
    });

    it('routes endowment gains through the 30% flat wrapper path', function () {
        $r = $this->engine->calculateInvestmentTax([
            'wrapper_code' => 'endowment',
            'gains' => 5_000_000,
            'dividends' => 0,
            'interest' => 0,
            'tax_year' => ZA_INV_TAX_YEAR,
        ]);

        expect($r['gains_tax'])->toBe(1_500_000);
        expect($r['total_tax'])->toBe(1_500_000);
    });

    it('applies 20% local DWT to local dividends', function () {
        $r = $this->engine->calculateInvestmentTax([
            'wrapper_code' => 'discretionary',
            'gains' => 0,
            'dividends' => 1_000_000,
            'interest' => 0,
            'tax_year' => ZA_INV_TAX_YEAR,
            'income_minor' => 40_000_000,
            'age' => 40,
        ]);

        expect($r['dividend_tax'])->toBe(200_000);
    });

    it('zeros dividend tax inside TFSA wrapper', function () {
        $r = $this->engine->calculateInvestmentTax([
            'wrapper_code' => 'tfsa',
            'gains' => 5_000_000,
            'dividends' => 1_000_000,
            'interest' => 100_000,
            'tax_year' => ZA_INV_TAX_YEAR,
            'income_minor' => 40_000_000,
            'age' => 40,
        ]);

        expect($r['total_tax'])->toBe(0);
        expect($r['gains_tax'])->toBe(0);
        expect($r['dividend_tax'])->toBe(0);
        expect($r['interest_tax'])->toBe(0);
    });

    it('composes interest tax through the Savings engine (exempt slice)', function () {
        $r = $this->engine->calculateInvestmentTax([
            'wrapper_code' => 'discretionary',
            'gains' => 0,
            'dividends' => 0,
            'interest' => 1_500_000,
            'tax_year' => ZA_INV_TAX_YEAR,
            'income_minor' => 40_000_000,
            'age' => 40,
        ]);

        expect($r['interest_tax'])->toBe(0);
    });
});

describe('Asset allocation rules', function () {
    it('returns empty rules (Reg 28 is WS 1.4 retirement-fund scope)', function () {
        expect($this->engine->getAssetAllocationRules())->toBe([]);
    });
});
