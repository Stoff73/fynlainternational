<?php

declare(strict_types=1);

use Fynla\Core\Contracts\ProtectionEngine;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Protection\ZaProtectionEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->engine = app(ZaProtectionEngine::class);
});

it('implements the ProtectionEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(ProtectionEngine::class);
});

it('lists the SA policy catalogue with dread + funeral', function () {
    $codes = array_column($this->engine->getAvailablePolicyTypes(), 'code');

    expect($codes)->toContain('life', 'dread', 'idisability_income', 'funeral');
});

describe('calculateCoverageNeeds', function () {
    it('computes life cover at 10× income + debts', function () {
        $r = $this->engine->calculateCoverageNeeds([
            'policy_type' => 'life',
            'annual_income' => 60_000_000,  // R600,000
            'outstanding_debts' => 100_000_000,  // R1,000,000
            'dependants' => 2,
            'existing_coverage' => 50_000_000,  // R500,000
            'age' => 40,
        ]);

        expect($r['recommended_cover'])->toBe(700_000_000);  // 10×R600k + R1m
        expect($r['shortfall'])->toBe(650_000_000);
    });

    it('computes income-protection at 75% of gross', function () {
        $r = $this->engine->calculateCoverageNeeds([
            'policy_type' => 'idisability_income',
            'annual_income' => 60_000_000,
            'outstanding_debts' => 0,
            'dependants' => 0,
            'existing_coverage' => 0,
            'age' => 40,
        ]);

        expect($r['recommended_cover'])->toBe(45_000_000);  // 75% of R600k
    });

    it('computes dread cover at 2× annual salary', function () {
        $r = $this->engine->calculateCoverageNeeds([
            'policy_type' => 'dread',
            'annual_income' => 60_000_000,
            'outstanding_debts' => 0,
            'dependants' => 0,
            'existing_coverage' => 0,
            'age' => 40,
        ]);

        expect($r['recommended_cover'])->toBe(120_000_000);  // 2× R600k
        expect($r['minimum_cover'])->toBe(60_000_000);  // 1× R600k
    });

    it('computes funeral cover at R30k × (dependants + member)', function () {
        $r = $this->engine->calculateCoverageNeeds([
            'policy_type' => 'funeral',
            'annual_income' => 0,
            'outstanding_debts' => 0,
            'dependants' => 3,
            'existing_coverage' => 0,
            'age' => 40,
        ]);

        expect($r['recommended_cover'])->toBe(12_000_000);  // 4 lives × R30k
    });

    it('rejects unknown policy type', function () {
        expect(fn () => $this->engine->calculateCoverageNeeds([
            'policy_type' => 'unicorn',
            'annual_income' => 0, 'outstanding_debts' => 0,
            'dependants' => 0, 'existing_coverage' => 0, 'age' => 40,
        ]))->toThrow(InvalidArgumentException::class);
    });
});

describe('getPolicyTaxTreatment', function () {
    it('returns tax-free treatment for dread', function () {
        $t = $this->engine->getPolicyTaxTreatment('dread');

        expect($t['premiums_deductible'])->toBeFalse();
        expect($t['payout_taxable'])->toBeFalse();
    });

    it('documents the s3(3)(a)(ii) exclusion for life cover', function () {
        $t = $this->engine->getPolicyTaxTreatment('life');

        expect($t['notes'])->toContain('s3(3)(a)(ii)');
    });
});
