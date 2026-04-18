<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Savings\ZaEmergencyFundCalculator;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->calc = app(ZaEmergencyFundCalculator::class);
});

describe('computeTarget', function () {
    it('defaults to 3 months when income is stable and dual-earner', function () {
        $r = $this->calc->computeTarget(
            essentialMonthlyExpenditureMinor: 3_000_000,
            incomeStability: 'stable',
            householdIncomeEarners: 2,
            uifEligible: true,
        );

        expect($r['target_months'])->toBe(3);
        expect($r['target_minor'])->toBe(9_000_000);
        expect($r['weighting_reason'])->toBe('dual_earner_stable');
    });

    it('weights to 6 months for single-earner households', function () {
        $r = $this->calc->computeTarget(
            essentialMonthlyExpenditureMinor: 3_000_000,
            incomeStability: 'stable',
            householdIncomeEarners: 1,
            uifEligible: true,
        );

        expect($r['target_months'])->toBe(6);
        expect($r['weighting_reason'])->toBe('single_earner');
    });

    it('weights to 6 months regardless of earners when income is volatile', function () {
        $r = $this->calc->computeTarget(
            essentialMonthlyExpenditureMinor: 3_000_000,
            incomeStability: 'volatile',
            householdIncomeEarners: 2,
            uifEligible: true,
        );

        expect($r['target_months'])->toBe(6);
        expect($r['weighting_reason'])->toBe('volatile_income');
    });

    it('adds one month when the earner is UIF-ineligible (self-employed)', function () {
        $r = $this->calc->computeTarget(
            essentialMonthlyExpenditureMinor: 3_000_000,
            incomeStability: 'stable',
            householdIncomeEarners: 2,
            uifEligible: false,
        );

        expect($r['target_months'])->toBe(4);
        expect($r['weighting_reason'])->toBe('uif_ineligible');
    });
});

describe('assess', function () {
    it('reports adequate when current balance covers the target', function () {
        $r = $this->calc->assess(
            currentBalanceMinor: 10_000_000,
            essentialMonthlyExpenditureMinor: 3_000_000,
            incomeStability: 'stable',
            householdIncomeEarners: 2,
            uifEligible: true,
        );

        expect($r['status'])->toBe('adequate');
        expect($r['shortfall_minor'])->toBe(0);
        expect($r['months_covered'])->toBe(3.33);
    });

    it('reports shortfall when balance is below target', function () {
        $r = $this->calc->assess(
            currentBalanceMinor: 5_000_000,
            essentialMonthlyExpenditureMinor: 3_000_000,
            incomeStability: 'stable',
            householdIncomeEarners: 1,
            uifEligible: true,
        );

        expect($r['status'])->toBe('shortfall');
        expect($r['shortfall_minor'])->toBe(13_000_000);
        expect($r['months_covered'])->toBe(1.67);
    });
});
