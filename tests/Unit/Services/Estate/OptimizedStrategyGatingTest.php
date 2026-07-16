<?php

declare(strict_types=1);

use Fynla\Packs\Gb\Database\Seeders\TaxConfigurationSeeder;
use Fynla\Packs\Gb\Estate\ComprehensiveEstatePlanService;
use Fynla\Packs\Gb\Models\Estate\IHTProfile;

/**
 * A sub-NRB estate owes no IHT, so trust/gifting mitigation saves nothing. The
 * "Immediate Actions (Year 1)" block was emitted unconditionally with a fixed
 * saving of availableNRB × rate, telling every user to transfer their NRB into
 * a discretionary trust to "save" IHT they don't owe. It must only appear for a
 * taxable estate, with savings capped at the actual liability.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(ComprehensiveEstatePlanService::class);
    $this->method = new ReflectionMethod($this->service, 'generateOptimizedStrategy');
    $this->method->setAccessible(true);

    $this->profile = new IHTProfile;
    $this->profile->available_nrb = 325000;

    $this->giftingPlan = ['summary' => ['total_gifted' => 0, 'total_iht_saved' => 0]];
    $this->trustPlan = ['strategies' => []];
});

it('omits the immediate-actions trust recommendation for a sub-NRB estate with no IHT liability', function () {
    $result = $this->method->invoke(
        $this->service,
        $this->giftingPlan,
        $this->trustPlan,
        null,
        0.0,
        $this->profile,
    );

    $categories = array_column($result['recommendations'], 'category');

    expect($categories)->not->toContain('Immediate Actions (Year 1)')
        ->and($result['summary']['total_iht_saving'])->toBe(0);
});

it('caps immediate-action savings at the actual IHT liability for a taxable estate', function () {
    // Liability £50k < availableNRB × 40% (£130k), so the trust saving must cap at £50k.
    $result = $this->method->invoke(
        $this->service,
        $this->giftingPlan,
        $this->trustPlan,
        null,
        50000.0,
        $this->profile,
    );

    $immediate = collect($result['recommendations'])->firstWhere('category', 'Immediate Actions (Year 1)');
    expect($immediate)->not->toBeNull();

    $trustAction = collect($immediate['actions'])->firstWhere('action', 'Establish discretionary trust within NRB');
    expect($trustAction['iht_saving'])->toBe(50000.0);
});
