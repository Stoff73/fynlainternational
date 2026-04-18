<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\SavingsAccount;
use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Savings\ZaSavingsEngine;
use Fynla\Packs\Za\Savings\ZaTfsaContributionTracker;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ZA_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
});

it('end-to-end: user records three contributions, engine reports no penalty, remaining allowances shrink', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->tfsa()->for($user)->create();
    $tracker = app(ZaTfsaContributionTracker::class);
    $engine = app(ZaSavingsEngine::class);

    $tracker->record($user->id, null, $account->id, ZA_TAX_YEAR, 1_000_000, '2026-04-10');
    $tracker->record($user->id, null, $account->id, ZA_TAX_YEAR, 1_500_000, '2026-07-15');
    $tracker->record($user->id, null, $account->id, ZA_TAX_YEAR, 1_000_000, '2026-12-01');

    expect($tracker->sumForTaxYear($user->id, null, ZA_TAX_YEAR))->toBe(3_500_000);
    expect($tracker->remainingAnnualAllowance($user->id, null, ZA_TAX_YEAR))->toBe(1_100_000);

    $r = $engine->calculateTaxFreeWrapperPenalty(
        contributionMinor: 500_000,
        annualPriorMinor: $tracker->sumForTaxYear($user->id, null, ZA_TAX_YEAR),
        lifetimePriorMinor: $tracker->sumLifetime($user->id, null),
        taxYear: ZA_TAX_YEAR,
    );

    expect($r['penalty_minor'])->toBe(0);
    expect($r['annual_remaining_minor'])->toBe(600_000);
});

it('end-to-end: engine flags a contribution that would breach the annual cap', function () {
    $user = User::factory()->create();
    $tracker = app(ZaTfsaContributionTracker::class);
    $engine = app(ZaSavingsEngine::class);

    $tracker->record($user->id, null, null, ZA_TAX_YEAR, 4_000_000, '2026-04-10');

    $r = $engine->calculateTaxFreeWrapperPenalty(
        contributionMinor: 1_000_000,
        annualPriorMinor: $tracker->sumForTaxYear($user->id, null, ZA_TAX_YEAR),
        lifetimePriorMinor: $tracker->sumLifetime($user->id, null),
        taxYear: ZA_TAX_YEAR,
    );

    expect($r['breached_cap'])->toBe('annual');
    expect($r['excess_minor'])->toBe(400_000);
    expect($r['penalty_minor'])->toBe(160_000);
});

it("end-to-end: a minor TFSA tracks the child's allowance, not the parent's", function () {
    $parent = User::factory()->create();
    $child = FamilyMember::factory()->for($parent)->create();
    $parentAccount = SavingsAccount::factory()->tfsa()->for($parent)->create();
    $childAccount = SavingsAccount::factory()
        ->tfsa()
        ->minor($child)
        ->for($parent)
        ->create();

    $tracker = app(ZaTfsaContributionTracker::class);

    // Parent contributes R30k to own TFSA and R40k to child's TFSA.
    $tracker->record($parent->id, null, $parentAccount->id, ZA_TAX_YEAR, 3_000_000, '2026-04-10');
    $tracker->record($parent->id, $child->id, $childAccount->id, ZA_TAX_YEAR, 4_000_000, '2026-04-11');

    // Parent's own remaining = R46k - R30k = R16k
    expect($tracker->remainingAnnualAllowance($parent->id, null, ZA_TAX_YEAR))->toBe(1_600_000);
    // Child's remaining = R46k - R40k = R6k
    expect($tracker->remainingAnnualAllowance($parent->id, $child->id, ZA_TAX_YEAR))->toBe(600_000);

    // Lifetime sums are independent.
    expect($tracker->sumLifetime($parent->id, null))->toBe(3_000_000);
    expect($tracker->sumLifetime($parent->id, $child->id))->toBe(4_000_000);
});
