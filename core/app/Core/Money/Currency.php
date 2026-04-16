<?php

declare(strict_types=1);

namespace Fynla\Core\Money;

use InvalidArgumentException;

/**
 * Immutable value object representing a currency.
 *
 * Carries the ISO 4217 code and the number of minor units
 * (decimal places) used by the currency.
 */
final class Currency
{
    /**
     * @param string $code       ISO 4217 currency code (e.g. "GBP", "ZAR", "USD")
     * @param int    $minorUnits Number of decimal places (2 for most currencies, 0 for JPY)
     */
    public function __construct(
        public readonly string $code,
        public readonly int $minorUnits,
    ) {
        if ($code === '' || strlen($code) !== 3) {
            throw new InvalidArgumentException("Currency code must be a 3-letter ISO 4217 code, got '{$code}'.");
        }

        if ($minorUnits < 0) {
            throw new InvalidArgumentException("Minor units cannot be negative, got {$minorUnits}.");
        }
    }

    /**
     * Create a GBP (British Pound Sterling) currency instance.
     */
    public static function GBP(): self
    {
        return new self('GBP', 2);
    }

    /**
     * Create a ZAR (South African Rand) currency instance.
     */
    public static function ZAR(): self
    {
        return new self('ZAR', 2);
    }

    /**
     * Create a USD (United States Dollar) currency instance.
     */
    public static function USD(): self
    {
        return new self('USD', 2);
    }

    /**
     * Create a currency instance from an ISO 4217 code.
     *
     * Uses a lookup table for known currencies. Falls back to 2 minor units
     * for unrecognised codes.
     *
     * @param string $code ISO 4217 currency code
     *
     * @return self
     */
    public static function from(string $code): self
    {
        $zeroMinor = ['JPY', 'KRW', 'VND', 'CLP', 'ISK'];
        $threeMinor = ['BHD', 'KWD', 'OMR'];

        $code = strtoupper($code);

        if (in_array($code, $zeroMinor, true)) {
            return new self($code, 0);
        }

        if (in_array($code, $threeMinor, true)) {
            return new self($code, 3);
        }

        return new self($code, 2);
    }
}
