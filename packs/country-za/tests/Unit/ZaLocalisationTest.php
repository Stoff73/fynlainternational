<?php

declare(strict_types=1);

use Fynla\Core\Contracts\Localisation;
use Fynla\Packs\Za\Localisation\ZaLocalisation;

beforeEach(function () {
    $this->loc = new ZaLocalisation();
});

it('implements the Localisation contract', function () {
    expect($this->loc)->toBeInstanceOf(Localisation::class);
});

it('reports ZAR + R symbol + en_ZA locale', function () {
    expect($this->loc->currencyCode())->toBe('ZAR');
    expect($this->loc->currencySymbol())->toBe('R');
    expect($this->loc->locale())->toBe('en_ZA');
});

it('formats money in SA convention (R space-thousands comma-decimal)', function () {
    expect($this->loc->formatMoney(123_456_789))->toBe('R 1 234 567,89');
    expect($this->loc->formatMoney(100))->toBe('R 1,00');
    expect($this->loc->formatMoney(0))->toBe('R 0,00');
});

it('handles negative amounts', function () {
    expect($this->loc->formatMoney(-12_345))->toBe('-R 123,45');
});

it('maps generic terms to SA equivalents', function () {
    $terms = $this->loc->getTerminology();

    expect($terms['retirement_wrapper'])->toBe('Retirement Annuity');
    expect($terms['tax_free_wrapper'])->toBe('Tax-Free Savings Account');
    expect($terms['estate_tax'])->toBe('Estate Duty');
    expect($terms['routing_code'])->toBe('Branch Code');
});
