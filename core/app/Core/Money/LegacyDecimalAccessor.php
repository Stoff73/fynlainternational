<?php

declare(strict_types=1);

namespace Fynla\Core\Money;

/**
 * Bridge for places where a decimal string is still the contract
 * (e.g., CSV exports, PDF report templates, API responses during migration).
 *
 * Converts Money back to a decimal string for legacy consumers.
 */
final class LegacyDecimalAccessor
{
    /**
     * Convert a Money value to a decimal string.
     * E.g., Money(123456, GBP) -> "1234.56"
     */
    public static function toDecimal(Money $money): string
    {
        $divisor = 10 ** $money->currency->minorUnits;

        return number_format($money->minor / $divisor, $money->currency->minorUnits, '.', '');
    }

    /**
     * Convert a Money value to a float.
     * WARNING: Use only for legacy interfaces that require float.
     * New code should NEVER use this.
     */
    public static function toFloat(Money $money): float
    {
        $divisor = 10 ** $money->currency->minorUnits;

        return $money->minor / $divisor;
    }

    /**
     * Convert from a legacy decimal/float value to Money.
     */
    public static function fromDecimal(float|string $decimal, string $currencyCode = 'GBP'): Money
    {
        return Money::ofMajor((string) $decimal, Currency::from($currencyCode));
    }
}
