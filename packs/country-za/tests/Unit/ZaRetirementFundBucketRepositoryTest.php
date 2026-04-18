<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaRetirementFundBucketRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function zaBucketCreateFundHolding(int $userId): int
{
    $pensionClass = '\\' . 'App' . '\\Models\\DCPension';
    $pension = $pensionClass::factory()->create([
        'user_id' => $userId,
        'country_code' => 'ZA',
    ]);

    return (int) $pension->id;
}

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    $this->repo = app(ZaRetirementFundBucketRepository::class);
    $userClass = '\\' . 'App' . '\\Models\\User';
    $this->user = $userClass::factory()->create();
    $this->fundHoldingId = zaBucketCreateFundHolding($this->user->id);
});

it('creates a zero-balance bucket for a new (user, fund holding) pair', function () {
    $bucket = $this->repo->findOrCreate($this->user->id, $this->fundHoldingId);

    expect($bucket->vested_balance_minor)->toBe(0);
    expect($bucket->provident_vested_pre2021_balance_minor)->toBe(0);
    expect($bucket->savings_balance_minor)->toBe(0);
    expect($bucket->retirement_balance_minor)->toBe(0);
    expect($bucket->balance_ccy)->toBe('ZAR');
});

it('returns the same row on subsequent calls (idempotent)', function () {
    $first = $this->repo->findOrCreate($this->user->id, $this->fundHoldingId);
    $second = $this->repo->findOrCreate($this->user->id, $this->fundHoldingId);

    expect($second->id)->toBe($first->id);
});

it('applies bucket deltas atomically', function () {
    $this->repo->applyDeltas(
        userId: $this->user->id,
        fundHoldingId: $this->fundHoldingId,
        vestedDeltaMinor: 5_000_000,
        savingsDeltaMinor: 1_000_000,
        retirementDeltaMinor: 2_000_000,
        transactionDate: '2026-04-10',
    );

    $bucket = $this->repo->findOrCreate($this->user->id, $this->fundHoldingId);
    expect($bucket->vested_balance_minor)->toBe(5_000_000);
    expect($bucket->savings_balance_minor)->toBe(1_000_000);
    expect($bucket->retirement_balance_minor)->toBe(2_000_000);
    expect($bucket->last_transaction_date?->format('Y-m-d'))->toBe('2026-04-10');
});

it('accumulates deltas across multiple calls', function () {
    $this->repo->applyDeltas($this->user->id, $this->fundHoldingId, 0, 500_000, 1_000_000, '2026-04-10');
    $this->repo->applyDeltas($this->user->id, $this->fundHoldingId, 0, 300_000, 600_000, '2026-05-10');

    $bucket = $this->repo->findOrCreate($this->user->id, $this->fundHoldingId);
    expect($bucket->savings_balance_minor)->toBe(800_000);
    expect($bucket->retirement_balance_minor)->toBe(1_600_000);
});

it('rejects deltas that would drive a bucket below zero', function () {
    $this->repo->applyDeltas($this->user->id, $this->fundHoldingId, 0, 500_000, 0, '2026-04-10');

    expect(fn () => $this->repo->applyDeltas(
        userId: $this->user->id,
        fundHoldingId: $this->fundHoldingId,
        vestedDeltaMinor: 0,
        savingsDeltaMinor: -600_000,
        retirementDeltaMinor: 0,
        transactionDate: '2026-05-10',
    ))->toThrow(InvalidArgumentException::class);
});
