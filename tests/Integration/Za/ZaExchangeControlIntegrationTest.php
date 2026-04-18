<?php

declare(strict_types=1);

use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControl;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
});

it('end-to-end: user records three SDA transfers, consumption reflects across the year', function () {
    $user = User::factory()->create();
    $ledger = app(ZaExchangeControlLedger::class);
    $excon = app(ZaExchangeControl::class);

    $ledger->record($user->id, 2026, 'sda', 50_000_000, '2026-02-01', 'GB', 'travel');
    $ledger->record($user->id, 2026, 'sda', 30_000_000, '2026-06-15', 'US', 'gift');
    $ledger->record($user->id, 2026, 'sda', 20_000_000, '2026-11-01', 'AU', 'travel');

    expect($excon->getAllowanceConsumed($user->id, '2026'))->toBe(100_000_000);
    expect($ledger->sumConsumed($user->id, 2026, 'sda'))->toBe(100_000_000);
    expect($ledger->sumConsumed($user->id, 2026, 'fia'))->toBe(0);
});

it('end-to-end: FIA transfer with AIT reference is recorded and flagged as approval-required', function () {
    $user = User::factory()->create();
    $ledger = app(ZaExchangeControlLedger::class);
    $excon = app(ZaExchangeControl::class);

    $ledger->record(
        userId: $user->id,
        calendarYear: 2026,
        allowanceType: 'fia',
        amountMinor: 500_000_000,
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

    expect($excon->requiresApproval(500_000_000, 'investment'))->toBeTrue();
    expect($ledger->sumConsumed($user->id, 2026, 'fia'))->toBe(500_000_000);
});

it('end-to-end: calendar year rollover — 2025 balances do not affect 2026 consumption', function () {
    $user = User::factory()->create();
    $ledger = app(ZaExchangeControlLedger::class);
    $excon = app(ZaExchangeControl::class);

    $ledger->record($user->id, 2025, 'sda', 200_000_000, '2025-12-20');

    expect($excon->getAllowanceConsumed($user->id, '2026'))->toBe(0);
    expect($excon->getAllowanceConsumed($user->id, '2025'))->toBe(200_000_000);
});
