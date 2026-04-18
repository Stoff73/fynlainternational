<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Investment\ZaCgtCalculator;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ZA_CGT_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->calc = app(ZaCgtCalculator::class);
});

describe('Discretionary CGT', function () {
    it('applies the R40,000 annual exclusion before 40% inclusion', function () {
        $r = $this->calc->calculateDiscretionaryCgt(
            gainMinor: 5_000_000,
            otherTaxableIncomeMinor: 40_000_000,
            age: 40,
            taxYear: ZA_CGT_TAX_YEAR,
        );

        expect($r['taxable_amount_minor'])->toBe(1_000_000);
        expect($r['included_minor'])->toBe(400_000);
        expect($r['tax_due_minor'])->toBeGreaterThan(0);
        expect($r['exclusion_applied_minor'])->toBe(4_000_000);
    });

    it('returns zero tax when gain is fully covered by annual exclusion', function () {
        $r = $this->calc->calculateDiscretionaryCgt(
            gainMinor: 3_500_000,
            otherTaxableIncomeMinor: 40_000_000,
            age: 40,
            taxYear: ZA_CGT_TAX_YEAR,
        );

        expect($r['taxable_amount_minor'])->toBe(0);
        expect($r['included_minor'])->toBe(0);
        expect($r['tax_due_minor'])->toBe(0);
        expect($r['exclusion_applied_minor'])->toBe(3_500_000);
    });

    it('returns zero tax on a loss', function () {
        $r = $this->calc->calculateDiscretionaryCgt(
            gainMinor: -2_000_000,
            otherTaxableIncomeMinor: 40_000_000,
            age: 40,
            taxYear: ZA_CGT_TAX_YEAR,
        );

        expect($r['taxable_amount_minor'])->toBe(0);
        expect($r['tax_due_minor'])->toBe(0);
    });
});

describe('Endowment CGT', function () {
    it('applies 30% flat rate with no annual exclusion', function () {
        $r = $this->calc->calculateEndowmentCgt(
            gainMinor: 5_000_000,
            taxYear: ZA_CGT_TAX_YEAR,
        );

        expect($r['tax_due_minor'])->toBe(1_500_000);
        expect($r['exclusion_applied_minor'])->toBe(0);
        expect($r['wrapper_rate_bps'])->toBe(3_000);
    });

    it('returns zero tax on a wrapper loss', function () {
        $r = $this->calc->calculateEndowmentCgt(
            gainMinor: -1_000_000,
            taxYear: ZA_CGT_TAX_YEAR,
        );

        expect($r['tax_due_minor'])->toBe(0);
    });
});
