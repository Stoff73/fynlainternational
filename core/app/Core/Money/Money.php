<?php

declare(strict_types=1);

namespace Fynla\Core\Money;

use InvalidArgumentException;

/**
 * Immutable value object representing a monetary amount in minor currency units.
 *
 * All arithmetic is performed on integers to avoid floating-point rounding
 * errors. Multiplication uses banker's rounding (round half to even) when
 * the result is not an exact integer.
 */
final class Money
{
    /**
     * @param int      $minor    Amount in minor currency units (e.g. pence, cents)
     * @param Currency $currency The currency this amount is denominated in
     */
    public function __construct(
        public readonly int $minor,
        public readonly Currency $currency,
    ) {
    }

    /**
     * Create a Money instance from a major-unit decimal string.
     *
     * @param string   $decimal  Amount in major units (e.g. "1234.56")
     * @param Currency $currency The currency
     *
     * @return self
     */
    public static function ofMajor(string $decimal, Currency $currency): self
    {
        $multiplier = 10 ** $currency->minorUnits;
        $minor = (int) round((float) $decimal * $multiplier);

        return new self($minor, $currency);
    }

    /**
     * Add another Money amount to this one.
     *
     * @param Money $other Must be the same currency
     *
     * @return self A new Money instance with the summed amount
     *
     * @throws InvalidArgumentException If currencies do not match
     */
    public function plus(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    /**
     * Subtract another Money amount from this one.
     *
     * @param Money $other Must be the same currency
     *
     * @return self A new Money instance with the difference
     *
     * @throws InvalidArgumentException If currencies do not match
     */
    public function minus(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    /**
     * Multiply this amount by a scalar, using banker's rounding.
     *
     * @param float|int $multiplier The multiplier
     *
     * @return self A new Money instance with the rounded product
     */
    public function times(float|int $multiplier): self
    {
        $result = $this->minor * $multiplier;

        // Banker's rounding (round half to even)
        $rounded = (int) round($result, 0, PHP_ROUND_HALF_EVEN);

        return new self($rounded, $this->currency);
    }

    /**
     * Check whether this amount is exactly zero.
     */
    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    /**
     * Check whether this amount is negative.
     */
    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    /**
     * Check whether this amount is positive.
     */
    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    /**
     * Check whether this amount equals another Money instance.
     *
     * @param Money $other Must be the same currency
     *
     * @return bool True if both amount and currency match
     *
     * @throws InvalidArgumentException If currencies do not match
     */
    public function equals(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor === $other->minor;
    }

    /**
     * Basic formatted string representation.
     *
     * For full locale-aware formatting, use the Localisation contract's formatMoney() method.
     *
     * @return string e.g. "GBP 1234.56" or "JPY 1000"
     */
    public function format(): string
    {
        $divisor = 10 ** $this->currency->minorUnits;

        if ($this->currency->minorUnits === 0) {
            return "{$this->currency->code} {$this->minor}";
        }

        $major = intdiv($this->minor, $divisor);
        $fraction = abs($this->minor % $divisor);

        $formatted = sprintf(
            "%s %s.%s",
            $this->currency->code,
            number_format($major, 0, '.', ','),
            str_pad((string) $fraction, $this->currency->minorUnits, '0', STR_PAD_LEFT),
        );

        return $formatted;
    }

    /**
     * Guard against arithmetic on mismatched currencies.
     *
     * @throws InvalidArgumentException
     */
    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency->code !== $other->currency->code) {
            throw new InvalidArgumentException(
                "Cannot perform arithmetic on different currencies: {$this->currency->code} vs {$other->currency->code}."
            );
        }
    }
}
