<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaContributionSplitService;
use Fynla\Packs\Za\Retirement\ZaRetirementFundBucketRepository;
use Fynla\Packs\Za\Retirement\ZaSavingsPotWithdrawalSimulator;
use Fynla\Packs\Za\Tax\ZaSection11fTracker;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ZA_RETIRE_INT_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
});

it('end-to-end: pre-two-pot + post-two-pot contributions populate buckets correctly', function () {
    $user = User::factory()->create();
    $fund = DCPension::factory()->create(['user_id' => $user->id, 'country_code' => 'ZA']);
    $splitter = app(ZaContributionSplitService::class);
    $buckets = app(ZaRetirementFundBucketRepository::class);

    $pre = $splitter->split(5_000_000, '2024-07-15');
    $buckets->applyDeltas(
        $user->id, $fund->id,
        $pre['vested_delta_minor'], $pre['savings_delta_minor'], $pre['retirement_delta_minor'],
        '2024-07-15',
    );

    $post = $splitter->split(3_000_000, '2026-05-10');
    $buckets->applyDeltas(
        $user->id, $fund->id,
        $post['vested_delta_minor'], $post['savings_delta_minor'], $post['retirement_delta_minor'],
        '2026-05-10',
    );

    $bucket = $buckets->findOrCreate($user->id, $fund->id);
    expect($bucket->vested_balance_minor)->toBe(5_000_000);
    expect($bucket->savings_balance_minor)->toBe(1_000_000);
    expect($bucket->retirement_balance_minor)->toBe(2_000_000);
    expect($buckets->totalBalanceMinor($user->id, $fund->id))->toBe(8_000_000);
});

it('end-to-end: Savings-Pot withdrawal composes marginal tax and decrements the savings bucket', function () {
    $user = User::factory()->create();
    $fund = DCPension::factory()->create(['user_id' => $user->id, 'country_code' => 'ZA']);
    $splitter = app(ZaContributionSplitService::class);
    $buckets = app(ZaRetirementFundBucketRepository::class);
    $sim = app(ZaSavingsPotWithdrawalSimulator::class);

    $split = $splitter->split(18_000_000, '2026-05-10');
    $buckets->applyDeltas(
        $user->id, $fund->id,
        $split['vested_delta_minor'], $split['savings_delta_minor'], $split['retirement_delta_minor'],
        '2026-05-10',
    );

    $result = $sim->simulate(3_000_000, 40_000_000, 40, ZA_RETIRE_INT_TAX_YEAR);

    expect($result['tax_delta_minor'])->toBeGreaterThan(0);
    expect($result['net_received_minor'])->toBeLessThan(3_000_000);

    $buckets->applyDeltas($user->id, $fund->id, 0, -3_000_000, 0, '2026-11-15');

    $bucket = $buckets->findOrCreate($user->id, $fund->id);
    expect($bucket->savings_balance_minor)->toBe(3_000_000);
    expect($bucket->retirement_balance_minor)->toBe(12_000_000);
});

it('end-to-end: Section 11F carry-forward composes tracker + engine (documentation pattern)', function () {
    $user = User::factory()->create();
    $tracker = app(ZaSection11fTracker::class);
    $taxEngine = app(ZaTaxEngine::class);

    // Year 1: fresh member, zero prior carry-forward.
    $priorCarry = $tracker->getCarryForward($user->id, ZA_RETIRE_INT_TAX_YEAR);
    expect($priorCarry)->toBe(0);

    // Engine is stateless — callers pass (contribution + carry) as grossMinor.
    $deduction = $taxEngine->calculateRetirementDeduction(
        40_000_000,
        ZA_RETIRE_INT_TAX_YEAR,
        $priorCarry,
    );

    expect($deduction['deductible_minor'])->toBe(35_000_000);  // R350k cap applied
    expect($deduction['carry_forward_minor'])->toBe(5_000_000);  // R50k rolls forward

    // Caller persists carry-forward for year 2 via the tracker.
    $tracker->setCarryForward($user->id, '2027/28', $deduction['carry_forward_minor']);

    // Year 2: tracker returns the rolled-forward R50k.
    expect($tracker->getCarryForward($user->id, '2027/28'))->toBe(5_000_000);
});
