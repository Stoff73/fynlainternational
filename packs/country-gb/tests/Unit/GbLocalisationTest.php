<?php

declare(strict_types=1);

use Fynla\Core\Contracts\Localisation;
use Fynla\Packs\Gb\Localisation\GbLocalisation;

beforeEach(function () {
    $this->loc = new GbLocalisation();
});

it('implements the Localisation contract', function () {
    expect($this->loc)->toBeInstanceOf(Localisation::class);
});

it('resolves from the container with the GB binding key', function () {
    expect(app('pack.gb.localisation'))->toBeInstanceOf(GbLocalisation::class);
});

it('reports GBP + £ symbol + en_GB locale + UK date format', function () {
    expect($this->loc->currencyCode())->toBe('GBP');
    expect($this->loc->currencySymbol())->toBe('£');
    expect($this->loc->locale())->toBe('en_GB');
    expect($this->loc->dateFormat())->toBe('d/m/Y');
});

it('formats money in UK convention (comma thousands, dot decimal)', function () {
    expect($this->loc->formatMoney(123_456))->toBe('£1,234.56');
    expect($this->loc->formatMoney(100))->toBe('£1.00');
    expect($this->loc->formatMoney(0))->toBe('£0.00');
    expect($this->loc->formatMoney(99))->toBe('£0.99');
    expect($this->loc->formatMoney(100_000_000))->toBe('£1,000,000.00');
});

it('handles negative amounts with sign before symbol', function () {
    expect($this->loc->formatMoney(-12_345))->toBe('-£123.45');
    expect($this->loc->formatMoney(-1))->toBe('-£0.01');
});

it('maps generic terms to UK equivalents', function () {
    $terms = $this->loc->getTerminology();

    expect($terms['retirement_wrapper'])->toBe('Personal Pension');
    expect($terms['tax_free_wrapper'])->toBe('Individual Savings Account');
    expect($terms['estate_tax'])->toBe('Inheritance Tax');
    expect($terms['routing_code'])->toBe('Sort Code');
    expect($terms['national_id'])->toBe('National Insurance Number');
    expect($terms['tax_authority'])->toBe('HMRC');
});
