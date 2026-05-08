<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Tax;

/**
 * Tracks Personal Allowance and tax band consumption as income sources are stacked.
 * Used for detailed per-income-type tax breakdowns.
 *
 * R-14a-Tax-iv: relocated from app/Services/ → packs/country-gb/src/Tax/.
 * Internal state is int-minor (pence). The public getters and the
 * `allocateIncome` return shape continue to expose float-pounds values
 * because both callers (UKTaxCalculator + RetirementIncomeService — both
 * R-14a-deferred) read them as pounds for downstream float arithmetic; the
 * full pence-shape contract migration lands when those services migrate.
 * `allocateIncome(int $incomeMinor)` takes pence on the way in (callers
 * convert via `(int) round($pounds * 100)`).
 */
class TaxBandTracker
{
    private int $personalAllowanceMinor;

    private int $basicRateLimitMinor;

    private int $higherRateLimitMinor;

    private float $basicRate;

    private float $higherRate;

    private float $additionalRate;

    private int $usedPersonalAllowanceMinor = 0;

    private int $usedBasicBandMinor = 0;

    private int $usedHigherBandMinor = 0;

    public function __construct(array $taxConfig)
    {
        $this->personalAllowanceMinor = self::poundsToMinor($taxConfig['personal_allowance']);
        $bands = $taxConfig['bands'];

        // Calculate absolute thresholds in pence
        $this->basicRateLimitMinor = $this->personalAllowanceMinor + self::poundsToMinor($bands[0]['max']);
        $this->higherRateLimitMinor = $this->personalAllowanceMinor + self::poundsToMinor($bands[1]['max']);

        $this->basicRate = (float) $bands[0]['rate'];
        $this->higherRate = (float) $bands[1]['rate'];
        $this->additionalRate = (float) $bands[2]['rate'];
    }

    /**
     * Get remaining personal allowance (pounds).
     */
    public function getRemainingPersonalAllowance(): float
    {
        return self::minorToPounds(max(0, $this->personalAllowanceMinor - $this->usedPersonalAllowanceMinor));
    }

    /**
     * Get remaining basic rate band capacity (pounds).
     */
    public function getRemainingBasicBand(): float
    {
        $totalBasicBandMinor = $this->basicRateLimitMinor - $this->personalAllowanceMinor;

        return self::minorToPounds(max(0, $totalBasicBandMinor - $this->usedBasicBandMinor));
    }

    /**
     * Get remaining higher rate band capacity (pounds).
     */
    public function getRemainingHigherBand(): float
    {
        $totalHigherBandMinor = $this->higherRateLimitMinor - $this->basicRateLimitMinor;

        return self::minorToPounds(max(0, $totalHigherBandMinor - $this->usedHigherBandMinor));
    }

    /**
     * Allocate income (pence) to bands and return breakdown with tax calculations (pounds output).
     * Consumes the bands for subsequent allocations.
     */
    public function allocateIncome(int $incomeMinor): array
    {
        if ($incomeMinor <= 0) {
            return $this->emptyAllocation();
        }

        $remainingMinor = $incomeMinor;
        $allocation = [
            'personal_allowance_used' => 0,
            'basic_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $this->basicRate],
            'higher_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $this->higherRate],
            'additional_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $this->additionalRate],
            'total_income_tax' => 0,
        ];

        // Use personal allowance first
        $paAvailableMinor = max(0, $this->personalAllowanceMinor - $this->usedPersonalAllowanceMinor);
        if ($paAvailableMinor > 0 && $remainingMinor > 0) {
            $paUsedMinor = min($remainingMinor, $paAvailableMinor);
            $allocation['personal_allowance_used'] = self::minorToPounds($paUsedMinor);
            $this->usedPersonalAllowanceMinor += $paUsedMinor;
            $remainingMinor -= $paUsedMinor;
        }

        // Use basic rate band
        $totalBasicBandMinor = $this->basicRateLimitMinor - $this->personalAllowanceMinor;
        $basicAvailableMinor = max(0, $totalBasicBandMinor - $this->usedBasicBandMinor);
        if ($basicAvailableMinor > 0 && $remainingMinor > 0) {
            $basicUsedMinor = min($remainingMinor, $basicAvailableMinor);
            $allocation['basic_rate']['taxable'] = self::minorToPounds($basicUsedMinor);
            $allocation['basic_rate']['tax'] = self::minorToPounds((int) round($basicUsedMinor * $this->basicRate));
            $this->usedBasicBandMinor += $basicUsedMinor;
            $remainingMinor -= $basicUsedMinor;
        }

        // Use higher rate band
        $totalHigherBandMinor = $this->higherRateLimitMinor - $this->basicRateLimitMinor;
        $higherAvailableMinor = max(0, $totalHigherBandMinor - $this->usedHigherBandMinor);
        if ($higherAvailableMinor > 0 && $remainingMinor > 0) {
            $higherUsedMinor = min($remainingMinor, $higherAvailableMinor);
            $allocation['higher_rate']['taxable'] = self::minorToPounds($higherUsedMinor);
            $allocation['higher_rate']['tax'] = self::minorToPounds((int) round($higherUsedMinor * $this->higherRate));
            $this->usedHigherBandMinor += $higherUsedMinor;
            $remainingMinor -= $higherUsedMinor;
        }

        // Additional rate for anything remaining
        if ($remainingMinor > 0) {
            $allocation['additional_rate']['taxable'] = self::minorToPounds($remainingMinor);
            $allocation['additional_rate']['tax'] = self::minorToPounds((int) round($remainingMinor * $this->additionalRate));
        }

        $allocation['total_income_tax'] = round(
            $allocation['basic_rate']['tax']
            + $allocation['higher_rate']['tax']
            + $allocation['additional_rate']['tax'],
            2
        );

        return $allocation;
    }

    /**
     * Get current band position for determining PSA and dividend rates.
     */
    public function getCurrentBandPosition(): string
    {
        $totalUsedMinor = $this->usedPersonalAllowanceMinor + $this->usedBasicBandMinor + $this->usedHigherBandMinor;

        if ($totalUsedMinor <= $this->personalAllowanceMinor) {
            return 'personal_allowance';
        }

        if ($totalUsedMinor <= $this->basicRateLimitMinor) {
            return 'basic';
        }

        if ($totalUsedMinor <= $this->higherRateLimitMinor) {
            return 'higher';
        }

        return 'additional';
    }

    /**
     * Get total income allocated so far (pounds).
     */
    public function getTotalAllocated(): float
    {
        return self::minorToPounds(
            $this->usedPersonalAllowanceMinor + $this->usedBasicBandMinor + $this->usedHigherBandMinor
        );
    }

    /**
     * Get tax configuration values for reference (pounds output).
     */
    public function getConfig(): array
    {
        return [
            'personal_allowance' => self::minorToPounds($this->personalAllowanceMinor),
            'basic_rate_limit' => self::minorToPounds($this->basicRateLimitMinor),
            'higher_rate_limit' => self::minorToPounds($this->higherRateLimitMinor),
            'basic_rate' => $this->basicRate,
            'higher_rate' => $this->higherRate,
            'additional_rate' => $this->additionalRate,
        ];
    }

    private function emptyAllocation(): array
    {
        return [
            'personal_allowance_used' => 0,
            'basic_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $this->basicRate],
            'higher_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $this->higherRate],
            'additional_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $this->additionalRate],
            'total_income_tax' => 0,
        ];
    }

    /**
     * Convert a pounds value (int / float / numeric string / null) to pence.
     */
    private static function poundsToMinor(int|float|string|null $pounds): int
    {
        return (int) round(((float) ($pounds ?? 0)) * 100);
    }

    /**
     * Convert pence to pounds rounded to the nearest penny (2dp).
     */
    private static function minorToPounds(int $minor): float
    {
        return round($minor / 100, 2);
    }
}
