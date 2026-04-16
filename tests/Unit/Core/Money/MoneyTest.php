<?php

declare(strict_types=1);

use Fynla\Core\Money\Currency;
use Fynla\Core\Money\Money;

describe('Money', function () {
    it('constructs with minor units and currency', function () {
        $gbp = Currency::GBP();
        $money = new Money(12345, $gbp);

        expect($money->minor)->toBe(12345);
        expect($money->currency->code)->toBe('GBP');
    });

    it('creates from major (decimal string) amount', function () {
        $money = Money::ofMajor('1234.56', Currency::GBP());

        expect($money->minor)->toBe(123456);
        expect($money->currency->code)->toBe('GBP');
    });

    it('creates from major amount for zero-minor currency', function () {
        $jpy = Currency::from('JPY');
        $money = Money::ofMajor('1000', $jpy);

        expect($money->minor)->toBe(1000);
    });

    it('adds two Money objects of same currency', function () {
        $a = new Money(1000, Currency::GBP());
        $b = new Money(2500, Currency::GBP());

        $result = $a->plus($b);

        expect($result->minor)->toBe(3500);
        expect($result->currency->code)->toBe('GBP');
    });

    it('subtracts two Money objects of same currency', function () {
        $a = new Money(5000, Currency::GBP());
        $b = new Money(1500, Currency::GBP());

        $result = $a->minus($b);

        expect($result->minor)->toBe(3500);
    });

    it('throws on addition with different currencies', function () {
        $gbp = new Money(1000, Currency::GBP());
        $zar = new Money(2000, Currency::ZAR());

        expect(fn () => $gbp->plus($zar))
            ->toThrow(InvalidArgumentException::class, 'Cannot perform arithmetic on different currencies: GBP vs ZAR.');
    });

    it('throws on subtraction with different currencies', function () {
        $gbp = new Money(1000, Currency::GBP());
        $usd = new Money(2000, Currency::USD());

        expect(fn () => $gbp->minus($usd))
            ->toThrow(InvalidArgumentException::class, 'Cannot perform arithmetic on different currencies: GBP vs USD.');
    });

    it('multiplies by integer', function () {
        $money = new Money(1000, Currency::GBP());

        $result = $money->times(3);

        expect($result->minor)->toBe(3000);
    });

    it('multiplies by float with bankers rounding', function () {
        // 1050 * 1.5 = 1575.0 (exact)
        $money = new Money(1050, Currency::GBP());
        expect($money->times(1.5)->minor)->toBe(1575);

        // Banker's rounding: round half to even
        // 5 * 0.5 = 2.5 -> rounds to 2 (even)
        $money2 = new Money(5, Currency::GBP());
        expect($money2->times(0.5)->minor)->toBe(2);

        // 15 * 0.5 = 7.5 -> rounds to 8 (even)
        $money3 = new Money(15, Currency::GBP());
        expect($money3->times(0.5)->minor)->toBe(8);
    });

    it('reports zero correctly', function () {
        $zero = new Money(0, Currency::GBP());
        $positive = new Money(100, Currency::GBP());
        $negative = new Money(-100, Currency::GBP());

        expect($zero->isZero())->toBeTrue();
        expect($positive->isZero())->toBeFalse();
        expect($negative->isZero())->toBeFalse();
    });

    it('reports negative correctly', function () {
        $negative = new Money(-100, Currency::GBP());
        $zero = new Money(0, Currency::GBP());
        $positive = new Money(100, Currency::GBP());

        expect($negative->isNegative())->toBeTrue();
        expect($zero->isNegative())->toBeFalse();
        expect($positive->isNegative())->toBeFalse();
    });

    it('reports positive correctly', function () {
        $positive = new Money(100, Currency::GBP());
        $zero = new Money(0, Currency::GBP());
        $negative = new Money(-100, Currency::GBP());

        expect($positive->isPositive())->toBeTrue();
        expect($zero->isPositive())->toBeFalse();
        expect($negative->isPositive())->toBeFalse();
    });

    it('checks equality', function () {
        $a = new Money(1000, Currency::GBP());
        $b = new Money(1000, Currency::GBP());
        $c = new Money(2000, Currency::GBP());

        expect($a->equals($b))->toBeTrue();
        expect($a->equals($c))->toBeFalse();
    });

    it('throws on equality check with different currencies', function () {
        $gbp = new Money(1000, Currency::GBP());
        $zar = new Money(1000, Currency::ZAR());

        expect(fn () => $gbp->equals($zar))
            ->toThrow(InvalidArgumentException::class);
    });

    it('formats to basic string', function () {
        $money = Money::ofMajor('1234.56', Currency::GBP());

        expect($money->format())->toBe('GBP 1,234.56');
    });

    it('formats zero-minor-unit currency', function () {
        $jpy = Currency::from('JPY');
        $money = new Money(1000, $jpy);

        expect($money->format())->toBe('JPY 1000');
    });

    it('formats negative amounts', function () {
        $money = new Money(-12345, Currency::GBP());

        expect($money->format())->toBe('GBP -123.45');
    });
});
