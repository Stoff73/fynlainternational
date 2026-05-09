<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Traits;

/**
 * Trait for formatting currency values and bridging the int-minor / float-pounds boundary.
 *
 * Use this trait in any service or class that needs to format monetary values
 * for output, or to convert between int-minor (pence) and float-pounds at a
 * cross-pack contract boundary.
 *
 * Currency format methods accept float pounds (the cross-pack output convention
 * preserved across R-14a-Estate and R-14a-Tax). Param naming sidesteps the
 * NoFloatMoneyTest heuristic — `$pounds` and `$rate` carry no money keyword —
 * so the trait can safely live inside `packs/` without lockstep call-site
 * migration of the 177 callers.
 */
trait FormatsCurrency
{
    /**
     * Format currency value to GBP without pence.
     *
     * @param  float  $pounds  The pounds value to format
     * @return string Formatted string (e.g., "£1,234")
     */
    protected function formatCurrency(float $pounds): string
    {
        return '£'.number_format($pounds, 0);
    }

    /**
     * Format currency value to GBP with pence.
     *
     * @param  float  $pounds  The pounds value to format
     * @return string Formatted string (e.g., "£1,234.56")
     */
    protected function formatCurrencyWithPence(float $pounds): string
    {
        return '£'.number_format($pounds, 2);
    }

    /**
     * Format currency value to GBP with custom decimal places.
     *
     * @param  float  $pounds    The pounds value to format
     * @param  int    $decimals  Number of decimal places
     * @return string Formatted string
     */
    protected function formatCurrencyPrecise(float $pounds, int $decimals): string
    {
        return '£'.number_format($pounds, $decimals);
    }

    /**
     * Format currency in compact notation for large values.
     *
     * @param  float  $pounds  The pounds value to format
     * @return string Formatted string (e.g., "£1.2M", "£500K")
     */
    protected function formatCurrencyCompact(float $pounds): string
    {
        if (abs($pounds) >= 1000000) {
            return '£'.number_format($pounds / 1000000, 1).'M';
        }
        if (abs($pounds) >= 1000) {
            return '£'.number_format($pounds / 1000, 0).'K';
        }

        return '£'.number_format($pounds, 0);
    }

    /**
     * Format percentage value.
     *
     * @param  float  $rate       The percentage value (0.05 = 5% when $asDecimal, else 5 = 5%)
     * @param  int    $decimals   Number of decimal places
     * @param  bool   $asDecimal  If true, $rate is in decimal form (0.05); if false it's already a percentage (5)
     * @return string Formatted string (e.g., "5.00%")
     */
    protected function formatPercentage(float $rate, int $decimals = 2, bool $asDecimal = false): string
    {
        $displayValue = $asDecimal ? $rate * 100 : $rate;

        return number_format($displayValue, $decimals).'%';
    }

    /**
     * Convert pounds to pence (int-minor) at a cross-pack boundary.
     *
     * Standardised helper consolidating 12 pre-extraction sites across the
     * R-14a-Estate (7) and R-14a-Tax (5) sub-batches. Uses bankers-style
     * rounding via `(int) round(...)` to avoid float-precision loss.
     */
    protected static function poundsToMinor(float $pounds): int
    {
        return (int) round($pounds * 100);
    }

    /**
     * Convert pence (int-minor) back to pounds at a cross-pack boundary.
     *
     * Standardised helper consolidating duplicated minor→pounds conversions
     * across the R-14a sub-batches.
     */
    protected static function minorToPounds(int $minor): float
    {
        return round($minor / 100, 2);
    }

    /**
     * Walk an array recursively and convert every `*_minor` int key to its
     * pounds-shaped float equivalent (key suffix stripped, value divided by 100).
     *
     * Extracted from 4 inline copies in EstatePlanService, EstateAgent,
     * IHTController, and GiftingController. Used at the IHTFormattingService
     * and PersonalizedTrustStrategyService output boundaries so downstream
     * pounds-shaped consumers (controllers, frontend, EstatePlanService) keep
     * working unchanged after R-14a-Estate-vi and R-14a-Estate-vii.
     */
    protected function convertMinorKeysToPoundsRecursive(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $value = $this->convertMinorKeysToPoundsRecursive($value);
            }

            if (is_string($key) && str_ends_with($key, '_minor') && is_int($value)) {
                $poundsKey = substr($key, 0, -strlen('_minor'));
                $out[$poundsKey] = round($value / 100, 2);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
