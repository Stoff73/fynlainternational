<?php

declare(strict_types=1);

use Fynla\Core\Money\Currency;

describe('Currency', function () {
    it('creates GBP with 2 minor units', function () {
        $gbp = Currency::GBP();

        expect($gbp->code)->toBe('GBP');
        expect($gbp->minorUnits)->toBe(2);
    });

    it('creates ZAR with 2 minor units', function () {
        $zar = Currency::ZAR();

        expect($zar->code)->toBe('ZAR');
        expect($zar->minorUnits)->toBe(2);
    });

    it('creates USD with 2 minor units', function () {
        $usd = Currency::USD();

        expect($usd->code)->toBe('USD');
        expect($usd->minorUnits)->toBe(2);
    });

    it('creates from string code', function () {
        $gbp = Currency::from('GBP');

        expect($gbp->code)->toBe('GBP');
        expect($gbp->minorUnits)->toBe(2);
    });

    it('normalises lowercase code to uppercase', function () {
        $gbp = Currency::from('gbp');

        expect($gbp->code)->toBe('GBP');
    });

    it('has correct minor units for JPY (0)', function () {
        $jpy = Currency::from('JPY');

        expect($jpy->code)->toBe('JPY');
        expect($jpy->minorUnits)->toBe(0);
    });

    it('has correct minor units for 3-decimal currencies', function () {
        $bhd = Currency::from('BHD');

        expect($bhd->code)->toBe('BHD');
        expect($bhd->minorUnits)->toBe(3);
    });

    it('defaults unknown currencies to 2 minor units', function () {
        $xyz = Currency::from('XYZ');

        expect($xyz->code)->toBe('XYZ');
        expect($xyz->minorUnits)->toBe(2);
    });

    it('rejects empty currency code', function () {
        expect(fn () => new Currency('', 2))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects invalid length currency code', function () {
        expect(fn () => new Currency('US', 2))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects negative minor units', function () {
        expect(fn () => new Currency('GBP', -1))
            ->toThrow(InvalidArgumentException::class);
    });
});
