<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Localisation contract for a jurisdiction.
 *
 * Provides currency formatting, date conventions, and a terminology
 * map that translates generic financial planning terms into the
 * local equivalents used in the jurisdiction.
 */
interface Localisation
{
    /**
     * ISO 4217 currency code for the jurisdiction (e.g. "GBP", "ZAR").
     */
    public function currencyCode(): string;

    /**
     * Currency symbol for display (e.g. "£", "R").
     */
    public function currencySymbol(): string;

    /**
     * POSIX locale identifier (e.g. "en_GB", "en_ZA").
     */
    public function locale(): string;

    /**
     * Date format string in PHP date() notation (e.g. "d/m/Y", "Y-m-d").
     */
    public function dateFormat(): string;

    /**
     * Format a monetary amount from minor currency units into a display string.
     *
     * @param int $minorUnits Amount in minor currency units (e.g. pence, cents)
     *
     * @return string Formatted string with currency symbol (e.g. "£1,234.56", "R 15 000,00")
     */
    public function formatMoney(int $minorUnits): string;

    /**
     * Get the terminology map translating generic terms to local equivalents.
     *
     * Keys are generic domain terms; values are the jurisdiction-specific labels.
     * This allows the UI to display locally meaningful product and concept names.
     *
     * Example mappings:
     * - "retirement_wrapper" => "Personal Pension" or "Retirement Annuity"
     * - "tax_free_wrapper" => "Individual Savings Account" or "Tax Free Savings Account"
     * - "estate_tax" => "Inheritance Tax" or "Estate Duty"
     * - "routing_code" => "Sort Code" or "Branch Code"
     *
     * @return array<string, string> Generic term => local term
     */
    public function getTerminology(): array;
}
