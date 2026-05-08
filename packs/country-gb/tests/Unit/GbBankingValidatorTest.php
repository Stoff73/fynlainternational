<?php

declare(strict_types=1);

use Fynla\Core\Contracts\BankingValidator;
use Fynla\Packs\Gb\Validation\GbBankingValidator;

beforeEach(function () {
    $this->v = new GbBankingValidator();
});

it('implements the BankingValidator contract', function () {
    expect($this->v)->toBeInstanceOf(BankingValidator::class);
});

it('resolves from the container with the GB banking binding', function () {
    expect(app('pack.gb.banking'))->toBeInstanceOf(GbBankingValidator::class);
});

it('accepts 8-digit account numbers', function () {
    expect($this->v->validateAccountNumber('12345678'))->toBeTrue();
    expect($this->v->validateAccountNumber('00000000'))->toBeTrue();
    expect($this->v->validateAccountNumber('1234 5678'))->toBeTrue();   // stripped whitespace
    expect($this->v->validateAccountNumber('1234-5678'))->toBeTrue();   // stripped hyphen
});

it('rejects account numbers of the wrong length', function () {
    expect($this->v->validateAccountNumber('1234567'))->toBeFalse();    // 7
    expect($this->v->validateAccountNumber('123456789'))->toBeFalse();  // 9
    expect($this->v->validateAccountNumber('abcdefgh'))->toBeFalse();
    expect($this->v->validateAccountNumber(''))->toBeFalse();
});

it('accepts 6-digit sort codes in plain or hyphenated form', function () {
    expect($this->v->validateRoutingCode('123456'))->toBeTrue();
    expect($this->v->validateRoutingCode('12-34-56'))->toBeTrue();
    expect($this->v->validateRoutingCode('12 34 56'))->toBeTrue();
});

it('rejects sort codes of the wrong length', function () {
    expect($this->v->validateRoutingCode('12345'))->toBeFalse();
    expect($this->v->validateRoutingCode('1234567'))->toBeFalse();
    expect($this->v->validateRoutingCode('AB-CD-EF'))->toBeFalse();
});

it('labels the routing code as "Sort Code" (UK convention)', function () {
    expect($this->v->getRoutingCodeLabel())->toBe('Sort Code');
});
