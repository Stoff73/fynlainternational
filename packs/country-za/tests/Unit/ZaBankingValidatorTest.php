<?php

declare(strict_types=1);

use Fynla\Core\Contracts\BankingValidator;
use Fynla\Packs\Za\Banking\ZaBankingValidator;

beforeEach(function () {
    $this->v = new ZaBankingValidator();
});

it('implements the BankingValidator contract', function () {
    expect($this->v)->toBeInstanceOf(BankingValidator::class);
});

it('accepts 7–11 digit account numbers', function () {
    expect($this->v->validateAccountNumber('1234567'))->toBeTrue();
    expect($this->v->validateAccountNumber('12345678901'))->toBeTrue();
    expect($this->v->validateAccountNumber('1234 5678 901'))->toBeTrue();  // stripped
});

it('rejects account numbers outside 7–11 digit range', function () {
    expect($this->v->validateAccountNumber('123456'))->toBeFalse();  // 6
    expect($this->v->validateAccountNumber('123456789012'))->toBeFalse();  // 12
    expect($this->v->validateAccountNumber('abcdefgh'))->toBeFalse();
});

it('accepts 6-digit universal branch code', function () {
    expect($this->v->validateRoutingCode('250655'))->toBeTrue();
    expect($this->v->validateRoutingCode('051001'))->toBeTrue();
});

it('rejects branch codes of wrong length', function () {
    expect($this->v->validateRoutingCode('12345'))->toBeFalse();
    expect($this->v->validateRoutingCode('1234567'))->toBeFalse();
});

it('labels the routing code as "Branch Code" (SA convention)', function () {
    expect($this->v->getRoutingCodeLabel())->toBe('Branch Code');
});
