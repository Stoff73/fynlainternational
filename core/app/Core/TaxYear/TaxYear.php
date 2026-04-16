<?php

declare(strict_types=1);

namespace Fynla\Core\TaxYear;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Immutable value object representing a tax year in a specific jurisdiction.
 *
 * Tax years vary by jurisdiction: some run April-to-April, others
 * March-to-February, others calendar-year. This object captures
 * the exact boundaries for any jurisdiction.
 */
final class TaxYear
{
    /**
     * @param string             $jurisdictionCode ISO 3166-1 alpha-2 code for the jurisdiction
     * @param string             $label            Human-readable label (e.g. "2025/26", "2026")
     * @param DateTimeImmutable  $startsOn         First day of the tax year (inclusive)
     * @param DateTimeImmutable  $endsOn           Last day of the tax year (inclusive)
     */
    public function __construct(
        public readonly string $jurisdictionCode,
        public readonly string $label,
        public readonly DateTimeImmutable $startsOn,
        public readonly DateTimeImmutable $endsOn,
    ) {
    }

    /**
     * Check whether a given date falls within this tax year.
     *
     * @param DateTimeInterface $date The date to check
     *
     * @return bool True if the date is on or between startsOn and endsOn (inclusive)
     */
    public function contains(DateTimeInterface $date): bool
    {
        return $date >= $this->startsOn && $date <= $this->endsOn;
    }

    /**
     * Check whether this tax year overlaps with another.
     *
     * @param TaxYear $other The other tax year to compare
     *
     * @return bool True if the two tax years share at least one day
     */
    public function overlaps(TaxYear $other): bool
    {
        return $this->startsOn <= $other->endsOn && $this->endsOn >= $other->startsOn;
    }
}
