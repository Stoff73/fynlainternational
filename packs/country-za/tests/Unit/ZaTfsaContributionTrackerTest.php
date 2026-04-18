<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Savings\ZaTfsaContributionTracker;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const TFSA_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->tracker = app(ZaTfsaContributionTracker::class);
    $userClass = '\\' . 'App' . '\\Models\\User';
    $this->user = $userClass::factory()->create();
});

it('records a contribution for an adult and sums correctly', function () {
    $id = $this->tracker->record(
        userId: $this->user->id,
        beneficiaryId: null,
        savingsAccountId: null,
        taxYear: TFSA_TAX_YEAR,
        amountMinor: 1_000_000,
        contributionDate: '2026-04-10',
    );

    expect($id)->toBeInt()->toBeGreaterThan(0);
    expect($this->tracker->sumForTaxYear($this->user->id, null, TFSA_TAX_YEAR))->toBe(1_000_000);
    expect($this->tracker->sumLifetime($this->user->id, null))->toBe(1_000_000);
});

it('accumulates multiple adult contributions in the same tax year', function () {
    $this->tracker->record($this->user->id, null, null, TFSA_TAX_YEAR, 1_500_000, '2026-04-10');
    $this->tracker->record($this->user->id, null, null, TFSA_TAX_YEAR, 2_500_000, '2026-07-15');

    expect($this->tracker->sumForTaxYear($this->user->id, null, TFSA_TAX_YEAR))->toBe(4_000_000);
});

it('isolates sums by tax year', function () {
    $this->tracker->record($this->user->id, null, null, '2025/26', 3_000_000, '2025-06-01');
    $this->tracker->record($this->user->id, null, null, TFSA_TAX_YEAR, 2_000_000, '2026-04-10');

    expect($this->tracker->sumForTaxYear($this->user->id, null, '2025/26'))->toBe(3_000_000);
    expect($this->tracker->sumForTaxYear($this->user->id, null, TFSA_TAX_YEAR))->toBe(2_000_000);
    expect($this->tracker->sumLifetime($this->user->id, null))->toBe(5_000_000);
});

it('isolates a minor TFSA allowance from the parent owner', function () {
    $familyMemberClass = '\\' . 'App' . '\\Models\\FamilyMember';
    $child = $familyMemberClass::factory()->for($this->user)->create();

    $this->tracker->record($this->user->id, null, null, TFSA_TAX_YEAR, 3_000_000, '2026-04-10');
    $this->tracker->record($this->user->id, $child->id, null, TFSA_TAX_YEAR, 4_000_000, '2026-04-11');

    expect($this->tracker->sumForTaxYear($this->user->id, null, TFSA_TAX_YEAR))->toBe(3_000_000);
    expect($this->tracker->sumForTaxYear($this->user->id, $child->id, TFSA_TAX_YEAR))->toBe(4_000_000);
    expect($this->tracker->sumLifetime($this->user->id, null))->toBe(3_000_000);
    expect($this->tracker->sumLifetime($this->user->id, $child->id))->toBe(4_000_000);
});

it('reports remaining allowances using pack config', function () {
    $this->tracker->record($this->user->id, null, null, TFSA_TAX_YEAR, 2_000_000, '2026-04-10');

    expect($this->tracker->remainingAnnualAllowance($this->user->id, null, TFSA_TAX_YEAR))
        ->toBe(2_600_000);
    expect($this->tracker->remainingLifetimeAllowance($this->user->id, null, TFSA_TAX_YEAR))
        ->toBe(48_000_000);
});

it('returns full allowance when the user has no contributions', function () {
    expect($this->tracker->remainingAnnualAllowance($this->user->id, null, TFSA_TAX_YEAR))
        ->toBe(4_600_000);
});

it('counts transfer_in rows toward the cap (SARS rule)', function () {
    $this->tracker->record(
        userId: $this->user->id,
        beneficiaryId: null,
        savingsAccountId: null,
        taxYear: TFSA_TAX_YEAR,
        amountMinor: 3_000_000,
        contributionDate: '2026-04-10',
        sourceType: 'transfer_in',
    );
    $this->tracker->record($this->user->id, null, null, TFSA_TAX_YEAR, 1_500_000, '2026-05-01');

    expect($this->tracker->sumForTaxYear($this->user->id, null, TFSA_TAX_YEAR))->toBe(4_500_000);
    expect($this->tracker->remainingAnnualAllowance($this->user->id, null, TFSA_TAX_YEAR))
        ->toBe(100_000);
});
