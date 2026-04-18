<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Investment\ZaBaseCostTracker;
use Fynla\Packs\Za\Investment\ZaInvestmentEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ZA_INV_INT_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
});

it('end-to-end: records two buys, computes weighted-average cost, sells and composes discretionary CGT', function () {
    $user = User::factory()->create();
    $account = InvestmentAccount::factory()->create(['user_id' => $user->id]);
    $holding = Holding::factory()->create([
        'holdable_type' => InvestmentAccount::class,
        'holdable_id' => $account->id,
    ]);

    $tracker = app(ZaBaseCostTracker::class);
    $engine = app(ZaInvestmentEngine::class);

    // Buy 100 @ R500 → cost R50,000 (5_000_000 cents).
    $tracker->recordPurchase($user->id, $holding->id, 100.0, 5_000_000, '2026-04-10');
    // Buy 200 @ R600 → cost R120,000 (12_000_000 cents).
    $tracker->recordPurchase($user->id, $holding->id, 200.0, 12_000_000, '2026-07-15');

    // Weighted avg = (5_000_000 + 12_000_000) / 300 = 56_666.67 cents/unit.
    expect(round($tracker->averageCostPerUnitMinor($holding->id), 2))
        ->toBe(56_666.67);

    // Sell 150 units at R800 = R120,000 proceeds (12_000_000 cents).
    $disposal = $tracker->recordDisposal($user->id, $holding->id, 150.0, '2026-09-01');
    $proceeds = 12_000_000;
    $gain = $proceeds - $disposal['cost_basis_removed_minor'];

    // R35,000 gain fully covered by R40,000 annual exclusion → zero CGT.
    $r = $engine->calculateInvestmentTax([
        'wrapper_code' => 'discretionary',
        'gains' => $gain,
        'dividends' => 0,
        'interest' => 0,
        'tax_year' => ZA_INV_INT_TAX_YEAR,
        'income_minor' => 40_000_000,
        'age' => 40,
    ]);

    expect($r['gains_tax'])->toBe(0);
    expect($r['breakdown']['wrapper_code'])->toBe('discretionary');
});

it('end-to-end: large endowment gain applies 30% flat with no exclusion', function () {
    $engine = app(ZaInvestmentEngine::class);

    // R100,000 endowment gain → 30% × R100,000 = R30,000.
    $r = $engine->calculateInvestmentTax([
        'wrapper_code' => 'endowment',
        'gains' => 10_000_000,
        'dividends' => 0,
        'interest' => 0,
        'tax_year' => ZA_INV_INT_TAX_YEAR,
    ]);

    expect($r['total_tax'])->toBe(3_000_000);
    expect($r['gains_tax'])->toBe(3_000_000);
});
