<?php

declare(strict_types=1);

use Fynla\Core\Contracts\SavingsEngine;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Savings\ZaSavingsEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ZA_SAVINGS_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->engine = app(ZaSavingsEngine::class);
});

it('implements the SavingsEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(SavingsEngine::class);
});

describe('Contract accessors', function () {
    it('returns the R46,000 annual cap', function () {
        expect($this->engine->getAnnualContributionCap(ZA_SAVINGS_TAX_YEAR))->toBe(4_600_000);
    });

    it('returns the R500,000 lifetime cap', function () {
        expect($this->engine->getLifetimeContributionCap(ZA_SAVINGS_TAX_YEAR))->toBe(50_000_000);
    });
});

describe('TFSA wrapper penalty', function () {
    it('returns zero penalty when under the annual cap', function () {
        $r = $this->engine->calculateTaxFreeWrapperPenalty(
            contributionMinor: 3_000_000,
            annualPriorMinor: 0,
            lifetimePriorMinor: 0,
            taxYear: ZA_SAVINGS_TAX_YEAR,
        );

        expect($r['penalty_minor'])->toBe(0);
        expect($r['excess_minor'])->toBe(0);
        expect($r['breached_cap'])->toBeNull();
        expect($r['annual_remaining_minor'])->toBe(1_600_000);
        expect($r['lifetime_remaining_minor'])->toBe(47_000_000);
    });

    it('applies 40% penalty on annual excess', function () {
        $r = $this->engine->calculateTaxFreeWrapperPenalty(
            contributionMinor: 5_000_000,
            annualPriorMinor: 0,
            lifetimePriorMinor: 0,
            taxYear: ZA_SAVINGS_TAX_YEAR,
        );

        expect($r['excess_minor'])->toBe(400_000);
        expect($r['penalty_minor'])->toBe(160_000);
        expect($r['breached_cap'])->toBe('annual');
    });

    it('applies 40% penalty on lifetime excess even when annual is fine', function () {
        $r = $this->engine->calculateTaxFreeWrapperPenalty(
            contributionMinor: 4_000_000,
            annualPriorMinor: 0,
            lifetimePriorMinor: 47_000_000,
            taxYear: ZA_SAVINGS_TAX_YEAR,
        );

        expect($r['excess_minor'])->toBe(1_000_000);
        expect($r['penalty_minor'])->toBe(400_000);
        expect($r['breached_cap'])->toBe('lifetime');
    });

    it('uses the larger excess when both caps are breached', function () {
        $r = $this->engine->calculateTaxFreeWrapperPenalty(
            contributionMinor: 10_000_000,
            annualPriorMinor: 0,
            lifetimePriorMinor: 45_000_000,
            taxYear: ZA_SAVINGS_TAX_YEAR,
        );

        expect($r['excess_minor'])->toBe(5_400_000);
        expect($r['penalty_minor'])->toBe(2_160_000);
        expect($r['breached_cap'])->toBe('annual');
    });
});

describe('Interest tax with exemption', function () {
    it('returns zero tax when interest is below the under-65 exemption', function () {
        $r = $this->engine->calculateInterestTax(
            interestMinor: 2_000_000,
            otherTaxableIncomeMinor: 30_000_000,
            age: 40,
            taxYear: ZA_SAVINGS_TAX_YEAR,
        );

        expect($r['taxable_interest_minor'])->toBe(0);
        expect($r['exemption_applied_minor'])->toBe(2_000_000);
        expect($r['tax_due_minor'])->toBe(0);
    });

    it('applies the 65+ exemption at R34,500', function () {
        $r = $this->engine->calculateInterestTax(
            interestMinor: 3_000_000,
            otherTaxableIncomeMinor: 20_000_000,
            age: 70,
            taxYear: ZA_SAVINGS_TAX_YEAR,
        );

        expect($r['taxable_interest_minor'])->toBe(0);
        expect($r['exemption_applied_minor'])->toBe(3_000_000);
        expect($r['tax_due_minor'])->toBe(0);
    });

    it('taxes interest above the exemption at the marginal rate', function () {
        $r = $this->engine->calculateInterestTax(
            interestMinor: 5_000_000,
            otherTaxableIncomeMinor: 40_000_000,
            age: 40,
            taxYear: ZA_SAVINGS_TAX_YEAR,
        );

        expect($r['taxable_interest_minor'])->toBe(2_620_000);
        expect($r['exemption_applied_minor'])->toBe(2_380_000);
        expect($r['tax_due_minor'])->toBeGreaterThan(0);
        expect($r['marginal_rate'])->toBe(31.0);
    });
});
