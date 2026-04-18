<?php

declare(strict_types=1);

use App\Services\Estate\UkEstateEngine;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Fynla\Core\Contracts\EstateEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    app(TaxConfigService::class)->clearCache();
    $this->engine = app(UkEstateEngine::class);
});

it('implements the EstateEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(EstateEngine::class);
});

it('returns UK NRB + RNRB + spousal exemptions', function () {
    $ex = $this->engine->getExemptions('2025/26');

    expect($ex)->toHaveKeys(['nrb', 'rnrb', 'spousal_transfer']);
    expect($ex['nrb']['value'])->toBeGreaterThanOrEqual(32_500_000);  // £325k
});

it('returns taper + charitable reliefs', function () {
    $r = $this->engine->getReliefs();

    expect($r)->toHaveKeys(['taper_relief', 'charitable_36pc']);
});

it('computes executor fees at 1.5% UK rule of thumb', function () {
    expect($this->engine->calculateExecutorFees(100_000_000))->toBe(1_500_000);
});

it('applies NRB and 40% IHT rate', function () {
    $r = $this->engine->calculateEstateTax([
        'gross_estate' => 100_000_000,  // £1m
        'liabilities' => 0,
        'exempt_transfers' => 0,
    ], '2025/26');

    // Chargeable £1m - £325k = £675k × 40% = £270,000
    expect($r['tax_due'])->toBe(27_000_000);
});
