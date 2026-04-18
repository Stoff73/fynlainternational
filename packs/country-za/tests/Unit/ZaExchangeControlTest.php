<?php

declare(strict_types=1);

use Fynla\Core\Contracts\ExchangeControl;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControl;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->excon = app(ZaExchangeControl::class);
    $this->ledger = app(ZaExchangeControlLedger::class);
    $userClass = '\\' . 'App' . '\\Models\\User';
    $this->user = $userClass::factory()->create();
});

it('implements the ExchangeControl contract', function () {
    expect($this->excon)->toBeInstanceOf(ExchangeControl::class);
});

describe('getAnnualAllowances', function () {
    it('returns SDA and FIA with current-year caps', function () {
        $allowances = $this->excon->getAnnualAllowances();

        expect($allowances)->toHaveKey('sda');
        expect($allowances)->toHaveKey('fia');
        expect($allowances['sda']['type'])->toBe('sda');
        expect($allowances['sda']['annual_limit'])->toBe(200_000_000);
        expect($allowances['sda']['currency'])->toBe('ZAR');
        expect($allowances['fia']['annual_limit'])->toBe(1_000_000_000);
    });
});

describe('checkTransferPermitted', function () {
    it('permits an SDA-sized ZAR→USD transfer when no prior consumption', function () {
        expect($this->excon->checkTransferPermitted(100_000_000, 'ZAR', 'USD'))->toBeTrue();
    });

    it('refuses a transfer above the SARB combined threshold', function () {
        // R15m single transfer — above R12m SARB threshold.
        expect($this->excon->checkTransferPermitted(1_500_000_000, 'ZAR', 'USD'))->toBeFalse();
    });

    it('permits a non-ZAR to non-ZAR transfer unconditionally (outside exchange-control regime)', function () {
        // A SA resident moving USD → EUR between offshore accounts is not
        // regulated by SA exchange control — no ZAR leaves the country.
        expect($this->excon->checkTransferPermitted(5_000_000_000, 'USD', 'EUR'))->toBeTrue();
    });
});

describe('getAllowanceConsumed', function () {
    it('reports consumed across both SDA and FIA within a calendar year', function () {
        $this->ledger->record($this->user->id, 2026, 'sda', 30_000_000, '2026-02-01');
        $this->ledger->record($this->user->id, 2026, 'fia', 500_000_000, '2026-06-01');

        expect($this->excon->getAllowanceConsumed($this->user->id, '2026'))->toBe(530_000_000);
    });

    it('returns zero when user has no ledger entries for the year', function () {
        expect($this->excon->getAllowanceConsumed($this->user->id, '2026'))->toBe(0);
    });

    it('accepts calendar year as integer or string', function () {
        $this->ledger->record($this->user->id, 2026, 'sda', 40_000_000, '2026-03-01');

        expect($this->excon->getAllowanceConsumed($this->user->id, '2026'))->toBe(40_000_000);
        expect($this->excon->getAllowanceConsumed($this->user->id, (string) 2026))->toBe(40_000_000);
    });
});

describe('requiresApproval', function () {
    it('requires AIT approval for FIA-type transfers', function () {
        expect($this->excon->requiresApproval(500_000_000, 'investment'))->toBeTrue();
    });

    it('does NOT require approval for SDA-sized transfers under R2m', function () {
        expect($this->excon->requiresApproval(150_000_000, 'travel'))->toBeFalse();
        expect($this->excon->requiresApproval(150_000_000, 'gift'))->toBeFalse();
    });

    it('requires SARB special approval above R12m combined threshold', function () {
        expect($this->excon->requiresApproval(1_500_000_000, 'investment'))->toBeTrue();
    });
});
