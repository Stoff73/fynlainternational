<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Goals\ZaGoalsDefaults;
use Fynla\Packs\Za\Goals\ZaSeveranceBenefitCalculator;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const GOALS_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->defaults = app(ZaGoalsDefaults::class);
    $this->severance = app(ZaSeveranceBenefitCalculator::class);
});

describe('ZaGoalsDefaults', function () {
    it('returns SA bond defaults (10% deposit, 20-year term)', function () {
        $bond = $this->defaults->getBondDefaults(GOALS_TAX_YEAR);

        expect($bond['deposit_pct'])->toBe(10.0);
        expect($bond['default_term_years'])->toBe(20);
    });

    it('returns tuition defaults', function () {
        $t = $this->defaults->getTuitionDefaults(GOALS_TAX_YEAR);

        expect($t['public_annual_minor'])->toBe(7_500_000);
        expect($t['private_annual_minor'])->toBe(15_000_000);
    });

    it('returns the R500k severance threshold', function () {
        expect($this->defaults->getSeveranceTaxFreeThresholdMinor(GOALS_TAX_YEAR))
            ->toBe(50_000_000);
    });
});

describe('ZaSeveranceBenefitCalculator', function () {
    it('computes zero tax on a R400k severance (within R500k threshold)', function () {
        $r = $this->severance->calculate(40_000_000, 0, GOALS_TAX_YEAR);

        expect($r['tax_due_minor'])->toBe(0);
        expect($r['tax_free_portion_minor'])->toBe(40_000_000);
        expect($r['net_received_minor'])->toBe(40_000_000);
    });

    it('applies the retirement lump sum table to amounts above the threshold', function () {
        // R800,000 severance, no prior — retirement-table 18% on slice
        // above R550k; 0% on first R550k.
        $r = $this->severance->calculate(80_000_000, 0, GOALS_TAX_YEAR);

        expect($r['tax_due_minor'])->toBeGreaterThan(0);
        expect($r['taxable_portion_minor'])->toBeGreaterThan(0);
        expect($r['net_received_minor'])->toBeLessThan(80_000_000);
    });

    it('threads prior cumulative lump sum receipts', function () {
        // R200k severance after R500k previously received — the first
        // R550k of the combined is 0%, so part of the severance crosses
        // into the 18% band.
        $r = $this->severance->calculate(20_000_000, 50_000_000, GOALS_TAX_YEAR);

        expect($r['tax_due_minor'])->toBeGreaterThan(0);
    });

    it('rejects negative inputs', function () {
        expect(fn () => $this->severance->calculate(-1, 0, GOALS_TAX_YEAR))
            ->toThrow(InvalidArgumentException::class);
    });
});
