<?php

declare(strict_types=1);

namespace Fynla\Core\Jurisdiction;

/**
 * Immutable value object representing a jurisdiction (country/territory)
 * that a user is associated with for financial planning purposes.
 */
final class Jurisdiction
{
    /**
     * @param string $code     ISO 3166-1 alpha-2 country code (e.g. "GB", "ZA")
     * @param string $name     Human-readable jurisdiction name (e.g. "United Kingdom", "South Africa")
     * @param string $currency ISO 4217 currency code (e.g. "GBP", "ZAR")
     * @param string $locale   POSIX locale identifier (e.g. "en_GB", "en_ZA")
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $currency,
        public readonly string $locale,
    ) {
    }
}
