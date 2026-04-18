<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    $this->ledger = app(ZaExchangeControlLedger::class);
    $userClass = '\\' . 'App' . '\\Models\\User';
    $this->user = $userClass::factory()->create();
});

it('records an SDA transfer and reflects it in the consumption sum', function () {
    $id = $this->ledger->record(
        userId: $this->user->id,
        calendarYear: 2026,
        allowanceType: 'sda',
        amountMinor: 50_000_000,
        transferDate: '2026-03-15',
        destinationCountry: 'GB',
        purpose: 'offshore_investment',
    );

    expect($id)->toBeInt()->toBeGreaterThan(0);
    expect($this->ledger->sumConsumed($this->user->id, 2026, 'sda'))->toBe(50_000_000);
});

it('isolates SDA from FIA consumption within the same calendar year', function () {
    $this->ledger->record($this->user->id, 2026, 'sda', 30_000_000, '2026-02-01');
    $this->ledger->record($this->user->id, 2026, 'fia', 500_000_000, '2026-06-01');

    expect($this->ledger->sumConsumed($this->user->id, 2026, 'sda'))->toBe(30_000_000);
    expect($this->ledger->sumConsumed($this->user->id, 2026, 'fia'))->toBe(500_000_000);
});

it('isolates by calendar year — 2025 balances do not leak into 2026', function () {
    $this->ledger->record($this->user->id, 2025, 'sda', 100_000_000, '2025-12-20');
    $this->ledger->record($this->user->id, 2026, 'sda', 40_000_000, '2026-01-15');

    expect($this->ledger->sumConsumed($this->user->id, 2025, 'sda'))->toBe(100_000_000);
    expect($this->ledger->sumConsumed($this->user->id, 2026, 'sda'))->toBe(40_000_000);
});

it('accumulates multiple entries in the same year/type', function () {
    $this->ledger->record($this->user->id, 2026, 'sda', 30_000_000, '2026-03-01');
    $this->ledger->record($this->user->id, 2026, 'sda', 70_000_000, '2026-06-01');
    $this->ledger->record($this->user->id, 2026, 'sda', 20_000_000, '2026-09-01');

    expect($this->ledger->sumConsumed($this->user->id, 2026, 'sda'))->toBe(120_000_000);
});

it('stores optional FIA metadata (AIT reference, authorised dealer, recipient account, document checklist)', function () {
    $this->ledger->record(
        userId: $this->user->id,
        calendarYear: 2026,
        allowanceType: 'fia',
        amountMinor: 200_000_000,
        transferDate: '2026-05-01',
        destinationCountry: 'US',
        purpose: 'offshore_investment',
        authorisedDealer: 'Investec Bank',
        recipientAccount: 'Investec Offshore USD Account ****7291',
        aitReference: 'AIT-2026-00123',
        aitDocuments: [
            'it14sd' => true,
            'it77c' => true,
            'tax_compliance_status_pin' => 'TCS-2026-ABCDE',
        ],
    );

    // Query via the Eloquent model so the `ait_documents` JSON cast kicks in.
    $row = \Fynla\Packs\Za\Models\ZaExchangeControlEntry::query()
        ->where('user_id', $this->user->id)
        ->first();

    expect($row->ait_reference)->toBe('AIT-2026-00123');
    expect($row->authorised_dealer)->toBe('Investec Bank');
    expect($row->destination_country)->toBe('US');
    expect($row->recipient_account)->toBe('Investec Offshore USD Account ****7291');
    expect($row->ait_documents)->toBeArray();
    expect($row->ait_documents['it14sd'])->toBeTrue();
    expect($row->ait_documents['tax_compliance_status_pin'])->toBe('TCS-2026-ABCDE');
});

it('rejects non-sda non-fia allowance_type values', function () {
    expect(fn () => $this->ledger->record(
        userId: $this->user->id,
        calendarYear: 2026,
        allowanceType: 'travel',
        amountMinor: 1_000_000,
        transferDate: '2026-03-01',
    ))->toThrow(InvalidArgumentException::class);
});
