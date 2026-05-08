<?php

declare(strict_types=1);

use Fynla\Packs\Gb\Models\TaxConfiguration;
use Fynla\Packs\Gb\Estate\FutureValueCalculator;

beforeEach(function () {
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $taxConfig = app(\Fynla\Packs\Gb\Tax\TaxConfigService::class);
    $this->calculator = new FutureValueCalculator($taxConfig);
});

it('calculates future value correctly', function () {
    $presentValueMinor = 10_000_000; // £100,000
    $growthRate = 0.05;
    $years = 10;

    $futureValueMinor = $this->calculator->calculateFutureValue($presentValueMinor, $growthRate, $years);

    // FV = 100000 * (1.05)^10 = 162,889.46 → 16,288,946 pence (rounded)
    expect($futureValueMinor)->toBeInt()
        ->and($futureValueMinor)->toBeGreaterThan($presentValueMinor)
        ->and($futureValueMinor)->toBeGreaterThan(16_280_000)
        ->and($futureValueMinor)->toBeLessThan(16_290_000);
});

it('returns present value when zero years', function () {
    $presentValueMinor = 5_000_000; // £50,000
    $futureValueMinor = $this->calculator->calculateFutureValue($presentValueMinor, 0.05, 0);

    expect($futureValueMinor)->toBe(5_000_000);
});

it('calculates portfolio future value', function () {
    $assets = collect([
        (object) ['asset_name' => 'Property', 'asset_type' => 'property', 'current_value' => 300000],
        (object) ['asset_name' => 'Investments', 'asset_type' => 'investment', 'current_value' => 100000],
        (object) ['asset_name' => 'Cash', 'asset_type' => 'cash', 'current_value' => 50000],
    ]);

    $result = $this->calculator->calculatePortfolioFutureValue($assets, 0.05, 10);

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['total_current_value_minor', 'total_future_value_minor', 'total_growth_minor', 'asset_projections'])
        ->and($result['total_current_value_minor'])->toBe(45_000_000) // £450k in pence
        ->and($result['total_future_value_minor'])->toBeGreaterThan(45_000_000)
        ->and($result['asset_projections'])->toHaveCount(3);
});

it('calculates portfolio future value with different growth rates by asset type', function () {
    $assets = collect([
        (object) ['asset_name' => 'Property', 'asset_type' => 'property', 'current_value' => 200000],
        (object) ['asset_name' => 'Stocks', 'asset_type' => 'investment', 'current_value' => 100000],
    ]);

    $growthRates = [
        'property' => 0.03,
        'investment' => 0.07,
        'default' => 0.05,
    ];

    $result = $this->calculator->calculatePortfolioFutureValueByAssetType($assets, $growthRates, 10);

    expect($result)->toBeArray()
        ->and($result['asset_projections'])->toHaveCount(2);

    $propertyProjection = collect($result['asset_projections'])->firstWhere('asset_type', 'property');
    $investmentProjection = collect($result['asset_projections'])->firstWhere('asset_type', 'investment');

    expect($propertyProjection['growth_rate'])->toBe(0.03)
        ->and($investmentProjection['growth_rate'])->toBe(0.07);
});

it('provides default growth rates', function () {
    $rates = $this->calculator->getDefaultGrowthRates();

    expect($rates)->toBeArray()
        ->and($rates)->toHaveKeys(['property', 'investment', 'cash', 'default'])
        ->and($rates['property'])->toBeFloat()
        ->and($rates['investment'])->toBeFloat();
});

it('calculates real future value adjusted for inflation', function () {
    $presentValueMinor = 10_000_000; // £100,000
    $nominalRate = 0.07;
    $inflationRate = 0.02;
    $years = 10;

    $realFvMinor = $this->calculator->calculateRealFutureValue($presentValueMinor, $nominalRate, $inflationRate, $years);
    $nominalFvMinor = $this->calculator->calculateFutureValue($presentValueMinor, $nominalRate, $years);

    expect($realFvMinor)->toBeInt()
        ->and($realFvMinor)->toBeGreaterThan($presentValueMinor)
        ->and($realFvMinor)->toBeLessThan($nominalFvMinor);
});

it('projects estate at death', function () {
    $assets = collect([
        (object) ['asset_name' => 'House', 'asset_type' => 'property', 'current_value' => 500000],
        (object) ['asset_name' => 'Pension', 'asset_type' => 'pension', 'current_value' => 200000],
    ]);

    $yearsUntilDeath = 20;

    $projection = $this->calculator->projectEstateAtDeath($assets, $yearsUntilDeath);

    expect($projection)->toBeArray()
        ->and($projection)->toHaveKeys([
            'current_estate_value_minor',
            'projected_estate_value_at_death_minor',
            'projected_growth_minor',
            'years_until_death',
            'asset_projections',
        ])
        ->and($projection['years_until_death'])->toBe(20)
        ->and($projection['projected_estate_value_at_death_minor'])->toBeGreaterThan($projection['current_estate_value_minor']);
});

it('calculates required growth rate to reach target', function () {
    $presentValueMinor = 10_000_000; // £100,000
    $targetValueMinor = 20_000_000; // £200,000 (double)
    $years = 10;

    $requiredCAGR = $this->calculator->calculateRequiredGrowthRate($presentValueMinor, $targetValueMinor, $years);

    // To double in 10 years: (2)^(1/10) - 1 ≈ 7.18%
    expect($requiredCAGR)->toBeFloat()
        ->and($requiredCAGR)->toBeGreaterThan(0.071)
        ->and($requiredCAGR)->toBeLessThan(0.073);
});
