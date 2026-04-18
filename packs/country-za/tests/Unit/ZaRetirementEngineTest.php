<?php

declare(strict_types=1);

use Fynla\Core\Contracts\RetirementEngine;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaRetirementEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ZA_RETIRE_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->engine = app(ZaRetirementEngine::class);
});

it('implements the RetirementEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(RetirementEngine::class);
});

describe('Contract methods', function () {
    it('exposes the Section 11F absolute cap as the annual allowance', function () {
        expect($this->engine->getAnnualAllowance(ZA_RETIRE_TAX_YEAR))->toBe(35_000_000);
    });

    it('returns null lifetime allowance', function () {
        expect($this->engine->getLifetimeAllowance(ZA_RETIRE_TAX_YEAR))->toBeNull();
    });

    it('returns age 60 as the SASSA Old Age Grant threshold regardless of gender/birth', function () {
        expect($this->engine->getStatePensionAge('1965-01-01', 'male'))->toBe(60);
        expect($this->engine->getStatePensionAge('1965-01-01', 'female'))->toBe(60);
        expect($this->engine->getStatePensionAge('1990-06-15', 'male'))->toBe(60);
    });
});

describe('calculatePensionTaxRelief', function () {
    it('computes Section 11F deductible within the R350k cap', function () {
        $r = $this->engine->calculatePensionTaxRelief(
            contributionMinor: 10_000_000,
            incomeMinor: 40_000_000,
            taxYear: ZA_RETIRE_TAX_YEAR,
        );

        expect($r['relief_amount'])->toBeGreaterThan(0);
        expect($r['method'])->toBe('section_11f');
        expect($r['net_cost'])->toBeLessThan(10_000_000);
    });

    it('caps relief at the R350k absolute limit', function () {
        $r = $this->engine->calculatePensionTaxRelief(
            contributionMinor: 50_000_000,
            incomeMinor: 200_000_000,
            taxYear: ZA_RETIRE_TAX_YEAR,
        );

        expect($r['relief_amount'])->toBeGreaterThan(0);
    });

    it('returns zero relief on a zero contribution', function () {
        $r = $this->engine->calculatePensionTaxRelief(0, 40_000_000, ZA_RETIRE_TAX_YEAR);

        expect($r['relief_amount'])->toBe(0);
        expect($r['net_cost'])->toBe(0);
    });
});

describe('projectPensionGrowth', function () {
    it('projects a 10-year growth with R12,000/year contribution at 8%', function () {
        $r = $this->engine->projectPensionGrowth([
            'current_value' => 5_000_000,
            'annual_contribution' => 1_200_000,
            'growth_rate' => 0.08,
            'years' => 10,
        ]);

        expect($r['projected_value'])->toBeGreaterThan(5_000_000);
        expect($r['year_by_year'])->toHaveCount(10);
        expect($r['year_by_year'][0]['year'])->toBe(1);
        expect($r['year_by_year'][9]['year'])->toBe(10);
    });
});
