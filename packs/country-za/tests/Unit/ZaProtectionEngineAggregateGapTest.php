<?php

declare(strict_types=1);

use Fynla\Packs\Za\Protection\ZaProtectionEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;

beforeEach(function () {
    $this->engine = new ZaProtectionEngine(app(ZaTaxConfigService::class));
});

it('aggregates coverage gap across the four primary categories with zero policies', function () {
    $result = $this->engine->calculateAggregateCoverageGap(
        userPolicies: [],
        userContext: ['annual_income' => 480_000, 'outstanding_debts' => 800_000, 'dependants' => 2],
    );

    expect($result)->toHaveKeys(['life', 'idisability_income', 'dread', 'funeral']);
    expect($result['life']['existing_cover'])->toBe(0);
    expect($result['life']['shortfall'])->toBeGreaterThan(0);
    expect($result['funeral']['recommended_cover'])->toBe(3 * 3_000_000); // 3 lives × R30k in minor units
});

it('sums existing cover of the same product type across multiple policies', function () {
    $policies = [
        ['product_type' => 'life', 'cover_amount_minor' => 2_000_000_00],
        ['product_type' => 'life', 'cover_amount_minor' => 3_000_000_00],
        ['product_type' => 'dread', 'cover_amount_minor' => 500_000_00],
    ];

    $result = $this->engine->calculateAggregateCoverageGap(
        userPolicies: $policies,
        userContext: ['annual_income' => 480_000, 'outstanding_debts' => 800_000, 'dependants' => 2],
    );

    expect($result['life']['existing_cover'])->toBe(5_000_000_00); // sum of both life policies
    expect($result['dread']['existing_cover'])->toBe(500_000_00);
});

it('flags missing_inputs when annual_income is zero or missing', function () {
    $result = $this->engine->calculateAggregateCoverageGap(
        userPolicies: [],
        userContext: ['annual_income' => 0, 'outstanding_debts' => 0, 'dependants' => 0],
    );

    expect($result['life']['missing_inputs'])->toContain('annual_income');
    expect($result['idisability_income']['missing_inputs'])->toContain('annual_income');
});

it('treats idisability_lump as sharing the dread calculation shape', function () {
    $policies = [
        ['product_type' => 'idisability_lump', 'cover_amount_minor' => 1_000_000_00],
    ];

    $result = $this->engine->calculateAggregateCoverageGap(
        userPolicies: $policies,
        userContext: ['annual_income' => 480_000, 'outstanding_debts' => 0, 'dependants' => 0],
    );

    // idisability_lump cover rolls into dread category per engine convention
    expect($result['dread']['existing_cover'])->toBe(1_000_000_00);
});
