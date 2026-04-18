<?php

declare(strict_types=1);

use App\Services\ExchangeControl\UkExchangeControl;
use Fynla\Core\Contracts\ExchangeControl;

beforeEach(function () {
    $this->excon = app(UkExchangeControl::class);
});

it('implements the ExchangeControl contract', function () {
    expect($this->excon)->toBeInstanceOf(ExchangeControl::class);
});

it('returns empty allowances for UK (no exchange control regime)', function () {
    expect($this->excon->getAnnualAllowances())->toBe([]);
});

it('permits all transfers (UK has no caps)', function () {
    expect($this->excon->checkTransferPermitted(100_000_000_00, 'GBP', 'USD'))->toBeTrue();
});

it('reports zero consumed for any user / period', function () {
    expect($this->excon->getAllowanceConsumed(1, '2026'))->toBe(0);
});

it('never requires approval', function () {
    expect($this->excon->requiresApproval(100_000_000_00, 'investment'))->toBeFalse();
});
