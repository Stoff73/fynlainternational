<?php

declare(strict_types=1);

use Fynla\Core\Contracts\EstateEngine;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Estate\ZaEstateEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const EST_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->engine = app(ZaEstateEngine::class);
});

it('implements the EstateEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(EstateEngine::class);
});

describe('calculateEstateTax', function () {
    it('applies the R3.5m abatement and 20% rate on a R5m estate (first death)', function () {
        $r = $this->engine->calculateEstateTax([
            'gross_estate' => 500_000_000,  // R5m
            'liabilities' => 0,
            'exempt_transfers' => 0,
        ], EST_TAX_YEAR);

        // Dutiable R5m - R3.5m abatement = R1.5m × 20% = R300,000
        expect($r['tax_due'])->toBe(30_000_000);
        expect($r['exemptions_applied']['abatement'])->toBe(350_000_000);
    });

    it('applies the 25% rate above the R30m threshold', function () {
        $r = $this->engine->calculateEstateTax([
            'gross_estate' => 4_000_000_000,  // R40m
            'liabilities' => 0,
            'exempt_transfers' => 0,
        ], EST_TAX_YEAR);

        // Net R40m - R3.5m = R36.5m
        // First R30m @ 20% = R6m
        // Remaining R6.5m @ 25% = R1.625m
        // Total R7.625m
        expect($r['tax_due'])->toBe(762_500_000);
    });

    it('applies portable abatement for second death', function () {
        $r = $this->engine->calculateEstateTax([
            'gross_estate' => 1_000_000_000,  // R10m
            'liabilities' => 0,
            'exempt_transfers' => 0,
            'has_predeceased_spouse' => true,
            'prior_spousal_abatement_used_minor' => 0,  // first spouse used none
        ], EST_TAX_YEAR);

        // Dutiable R10m - R7m (double abatement) = R3m × 20% = R600,000
        expect($r['tax_due'])->toBe(60_000_000);
    });

    it('applies unlimited spousal exemption', function () {
        $r = $this->engine->calculateEstateTax([
            'gross_estate' => 1_000_000_000,
            'liabilities' => 0,
            'exempt_transfers' => 0,
            'spouse_transfer' => 1_000_000_000,
        ], EST_TAX_YEAR);

        expect($r['tax_due'])->toBe(0);
    });
});

describe('calculateCgtOnDeath', function () {
    it('applies R300k death exclusion + 40% inclusion', function () {
        // R500,000 deemed gain, R200,000 other income.
        // Exclude R300k → R200k taxable × 40% = R80k included.
        // Tax delta at R200k + R80k = R280k vs R200k income.
        $r = $this->engine->calculateCgtOnDeath(
            deemedGainMinor: 50_000_000,
            otherTaxableIncomeMinor: 20_000_000,
            taxYear: EST_TAX_YEAR,
        );

        expect($r['taxable_amount_minor'])->toBe(20_000_000);
        expect($r['included_minor'])->toBe(8_000_000);
        expect($r['exclusion_applied_minor'])->toBe(30_000_000);
        expect($r['tax_due_minor'])->toBeGreaterThan(0);
    });

    it('returns zero when deemed gain is within R300k exclusion', function () {
        $r = $this->engine->calculateCgtOnDeath(25_000_000, 20_000_000, EST_TAX_YEAR);

        expect($r['taxable_amount_minor'])->toBe(0);
        expect($r['tax_due_minor'])->toBe(0);
    });
});

describe('calculateExecutorFees', function () {
    it('applies 3.5% + 15% VAT to gross estate', function () {
        // R10m × 3.5% = R350,000. Plus 15% VAT = R402,500.
        $fees = $this->engine->calculateExecutorFees(1_000_000_000);

        expect($fees)->toBe(40_250_000);  // R402,500
    });

    it('returns zero on a zero estate', function () {
        expect($this->engine->calculateExecutorFees(0))->toBe(0);
    });
});

describe('getExemptions', function () {
    it('returns abatement + spousal + CGT death exclusion', function () {
        $ex = $this->engine->getExemptions(EST_TAX_YEAR);

        expect($ex)->toHaveKeys(['abatement', 'spousal_transfer', 'cgt_death_exclusion']);
        expect($ex['abatement']['value'])->toBe(350_000_000);
        expect($ex['cgt_death_exclusion']['value'])->toBe(30_000_000);
    });
});

describe('getReliefs', function () {
    it('returns abatement portability + spousal CGT rollover', function () {
        $r = $this->engine->getReliefs();

        expect($r)->toHaveKeys(['abatement_portability', 'spousal_rollover_cgt']);
    });
});
