<?php

declare(strict_types=1);

use Fynla\Core\Money\Currency;
use Fynla\Core\Money\LegacyDecimalAccessor;
use Fynla\Core\Money\Money;

describe('LegacyDecimalAccessor', function () {
    it('converts Money to decimal string', function () {
        $money = new Money(123456, Currency::GBP());

        $result = LegacyDecimalAccessor::toDecimal($money);

        expect($result)->toBe('1234.56');
    });

    it('converts Money to float', function () {
        // WARNING: toFloat() exists only for legacy interfaces.
        // New code should use Money objects directly.
        $money = new Money(123456, Currency::GBP());

        $result = LegacyDecimalAccessor::toFloat($money);

        expect($result)->toBe(1234.56);
    });

    it('converts decimal string back to Money', function () {
        $money = LegacyDecimalAccessor::fromDecimal('1234.56', 'GBP');

        expect($money)->toBeInstanceOf(Money::class);
        expect($money->minor)->toBe(123456);
        expect($money->currency->code)->toBe('GBP');
    });

    it('handles zero amounts', function () {
        $money = new Money(0, Currency::GBP());

        expect(LegacyDecimalAccessor::toDecimal($money))->toBe('0.00');
        expect(LegacyDecimalAccessor::toFloat($money))->toBe(0.0);

        $roundTrip = LegacyDecimalAccessor::fromDecimal('0.00', 'GBP');
        expect($roundTrip->minor)->toBe(0);
    });

    it('handles negative amounts', function () {
        $money = new Money(-50075, Currency::GBP());

        expect(LegacyDecimalAccessor::toDecimal($money))->toBe('-500.75');
        expect(LegacyDecimalAccessor::toFloat($money))->toBe(-500.75);

        $roundTrip = LegacyDecimalAccessor::fromDecimal('-500.75', 'GBP');
        expect($roundTrip->minor)->toBe(-50075);
    });

    it('handles JPY with 0 decimal places', function () {
        $jpy = Currency::from('JPY');
        $money = new Money(1000, $jpy);

        expect(LegacyDecimalAccessor::toDecimal($money))->toBe('1000');
        expect(LegacyDecimalAccessor::toFloat($money))->toBe(1000.0);

        $roundTrip = LegacyDecimalAccessor::fromDecimal('1000', 'JPY');
        expect($roundTrip->minor)->toBe(1000);
        expect($roundTrip->currency->minorUnits)->toBe(0);
    });
});
