<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Investment\ZaBaseCostTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function zaLotsCreateHolding(int $userId): int
{
    $accountClass = '\\' . 'App' . '\\Models\\Investment\\InvestmentAccount';
    $account = $accountClass::factory()->create(['user_id' => $userId]);

    $holdingClass = '\\' . 'App' . '\\Models\\Investment\\Holding';
    $holding = $holdingClass::factory()->create([
        'holdable_type' => $accountClass,
        'holdable_id' => $account->id,
    ]);

    return (int) $holding->id;
}

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    $this->tracker = app(ZaBaseCostTracker::class);
    $userClass = '\\' . 'App' . '\\Models\\User';
    $this->user = $userClass::factory()->create();
    $this->holdingId = zaLotsCreateHolding($this->user->id);
});

it('records a lot and returns it in openLots()', function () {
    $lotId = $this->tracker->recordPurchase(
        userId: $this->user->id,
        holdingId: $this->holdingId,
        quantity: 100.0,
        costMinor: 5_000_000,
        acquisitionDate: '2026-04-10',
    );

    expect($lotId)->toBeInt()->toBeGreaterThan(0);

    $lots = $this->tracker->openLots($this->holdingId);
    expect($lots)->toHaveCount(1);
    expect($lots[0]['quantity_open'])->toBe(100.0);
    expect($lots[0]['acquisition_cost_minor'])->toBe(5_000_000);
});

it('computes weighted-average cost per unit across multiple lots', function () {
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 100.0, 500_000, '2026-04-10',
    );
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 200.0, 1_600_000, '2026-06-15',
    );

    // (100*5000 + 200*8000) / 300 = (500_000 + 1_600_000) / 300 = 7_000/unit
    $avg = $this->tracker->averageCostPerUnitMinor($this->holdingId);

    expect($avg)->toBe(7_000.0);
});

it('applies a partial disposal proportionally across open lots', function () {
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 100.0, 500_000, '2026-04-10',
    );
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 200.0, 1_600_000, '2026-06-15',
    );

    // Dispose 150 units. At weighted average 7_000/unit, cost basis
    // removed = 150 * 7000 = 1_050_000 cents.
    $result = $this->tracker->recordDisposal(
        userId: $this->user->id,
        holdingId: $this->holdingId,
        quantity: 150.0,
        disposalDate: '2026-09-01',
    );

    expect($result['cost_basis_removed_minor'])->toBe(1_050_000);
    expect($result['units_disposed'])->toBe(150.0);

    // 150 units remain at weighted average 7_000/unit.
    $avg = $this->tracker->averageCostPerUnitMinor($this->holdingId);
    expect($avg)->toBe(7_000.0);
});

it('syncs holdings.cost_basis with the open-lot cost basis after a disposal', function () {
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 100.0, 500_000, '2026-04-10',
    );
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 200.0, 1_600_000, '2026-06-15',
    );

    // Dispose 150 units → 150 remain, open cost basis = 150 * 7000 = 1_050_000.
    $this->tracker->recordDisposal(
        userId: $this->user->id,
        holdingId: $this->holdingId,
        quantity: 150.0,
        disposalDate: '2026-09-01',
    );

    $holding = \DB::table('holdings')->where('id', $this->holdingId)->first();
    expect((int) round($holding->cost_basis * 100))->toBe(1_050_000);
});

it('rejects disposal exceeding open quantity', function () {
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 50.0, 250_000, '2026-04-10',
    );

    expect(fn () => $this->tracker->recordDisposal(
        userId: $this->user->id,
        holdingId: $this->holdingId,
        quantity: 100.0,
        disposalDate: '2026-09-01',
    ))->toThrow(InvalidArgumentException::class);
});

it('isolates lots by holding_id', function () {
    $otherHolding = zaLotsCreateHolding($this->user->id);

    $this->tracker->recordPurchase($this->user->id, $this->holdingId, 100.0, 500_000, '2026-04-10');
    $this->tracker->recordPurchase($this->user->id, $otherHolding, 50.0, 250_000, '2026-04-11');

    expect($this->tracker->openLots($this->holdingId))->toHaveCount(1);
    expect($this->tracker->openLots($otherHolding))->toHaveCount(1);
    expect($this->tracker->averageCostPerUnitMinor($this->holdingId))->toBe(5_000.0);
    expect($this->tracker->averageCostPerUnitMinor($otherHolding))->toBe(5_000.0);
});
