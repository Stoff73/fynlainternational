<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Estate;

use Fynla\Packs\Gb\Models\Estate\Gift;
use Fynla\Packs\Gb\Models\Estate\IHTProfile;
use Fynla\Packs\Gb\Tax\TaxConfigService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * GiftingStrategy Service
 *
 * Analyzes and optimizes gifting strategies for IHT planning. Handles PET
 * analysis, annual exemptions, and strategy recommendations.
 *
 * Money values are passed and returned in minor units (pence) per ADR-005.
 * The service reads its config values from `TaxConfigService` (which still
 * exposes pounds-as-float) and converts at the read site; the round-trip is
 * always lossless because every config value is a whole-pound figure.
 */
class GiftingStrategy
{
    private array $ihtConfig;

    private array $giftingConfig;

    public function __construct(
        private ?TaxConfigService $taxConfig = null
    ) {
        if ($this->taxConfig === null) {
            $this->taxConfig = app(TaxConfigService::class);
        }

        $this->ihtConfig = $this->taxConfig->getInheritanceTax();
        $this->giftingConfig = $this->taxConfig->getGiftingExemptions();
    }

    /**
     * Analyze Potentially Exempt Transfers (PETs).
     *
     * @param  Collection  $gifts  Collection of Gift models
     */
    public function analyzePETs(Collection $gifts): array
    {
        $now = Carbon::now();

        $activePets = $gifts->filter(function ($gift) use ($now) {
            if (($gift->gift_type ?? '') !== 'pet') {
                return false;
            }

            $giftDate = Carbon::parse($gift->gift_date);
            $yearsAgo = $giftDate->diffInYears($now);

            return $yearsAgo < 7;
        });

        $petsData = $activePets->map(function ($gift) use ($now) {
            $giftDate = Carbon::parse($gift->gift_date);
            $yearsAgo = (int) $giftDate->diffInYears($now);
            $yearsRemaining = 7 - $yearsAgo;

            return [
                'id' => $gift->id,
                'gift_date' => $gift->gift_date,
                'recipient' => $gift->recipient,
                'gift_value_minor' => self::poundsToMinor($gift->gift_value ?? 0),
                'years_ago' => $yearsAgo,
                'years_remaining' => max(0, $yearsRemaining),
                'taper_relief_applicable' => $yearsAgo >= 3,
            ];
        })->values();

        $totalValueMinor = $activePets->reduce(
            fn (int $carry, $gift) => $carry + self::poundsToMinor($gift->gift_value ?? 0),
            0
        );

        return [
            'active_pets_count' => $activePets->count(),
            'total_pet_value_minor' => $totalValueMinor,
            'pets' => $petsData->toArray(),
        ];
    }

    /**
     * Calculate annual exemption available for a tax year.
     *
     * @param  int  $userId
     * @param  string  $taxYear  Tax year (e.g., '2024')
     * @return int Available exemption including carry forward, in pence.
     */
    public function calculateAnnualExemption(int $userId, string $taxYear): int
    {
        $annualExemptionMinor = self::poundsToMinor($this->giftingConfig['annual_exemption']);

        $startYear = (int) $taxYear;
        $taxYearStart = Carbon::createFromDate($startYear, 4, 6);
        $taxYearEnd = $taxYearStart->copy()->addYear()->subDay();

        $prevTaxYearStart = $taxYearStart->copy()->subYear();
        $prevTaxYearEnd = $taxYearStart->copy()->subDay();

        $currentYearGiftsMinor = self::poundsToMinor(
            (float) Gift::where('user_id', $userId)
                ->whereBetween('gift_date', [$taxYearStart, $taxYearEnd])
                ->sum('gift_value')
        );

        $previousYearGiftsMinor = self::poundsToMinor(
            (float) Gift::where('user_id', $userId)
                ->whereBetween('gift_date', [$prevTaxYearStart, $prevTaxYearEnd])
                ->sum('gift_value')
        );

        $carryForwardMinor = max(0, $annualExemptionMinor - $previousYearGiftsMinor);
        $totalAvailableMinor = $annualExemptionMinor + $carryForwardMinor;

        return max(0, $totalAvailableMinor - $currentYearGiftsMinor);
    }

    /**
     * Identify and validate small gifts (£250 per recipient per year).
     *
     * @param  Collection  $gifts  Collection of Gift models
     */
    public function identifySmallGifts(Collection $gifts): array
    {
        $smallGiftsLimitMinor = self::poundsToMinor($this->giftingConfig['small_gifts_limit']);

        $smallGifts = $gifts->filter(fn ($gift) => ($gift->gift_type ?? '') === 'small_gift');

        $byRecipient = $smallGifts->groupBy('recipient')->map(function ($recipientGifts, $recipient) use ($smallGiftsLimitMinor) {
            $totalValueMinor = $recipientGifts->reduce(
                fn (int $carry, $gift) => $carry + self::poundsToMinor($gift->gift_value ?? 0),
                0
            );
            $isValid = $totalValueMinor <= $smallGiftsLimitMinor;

            return [
                'recipient' => $recipient,
                'total_value_minor' => $totalValueMinor,
                'gift_count' => $recipientGifts->count(),
                'is_valid' => $isValid,
                'warning' => $isValid ? null : "Exceeds £250 limit for recipient: {$recipient}",
            ];
        })->values();

        $totalValueMinor = $smallGifts->reduce(
            fn (int $carry, $gift) => $carry + self::poundsToMinor($gift->gift_value ?? 0),
            0
        );

        return [
            'small_gifts_count' => $smallGifts->count(),
            'total_value_minor' => $totalValueMinor,
            'by_recipient' => $byRecipient->toArray(),
        ];
    }

    /**
     * Calculate wedding/civil partnership gift allowance based on relationship.
     *
     * @return int Allowable gift amount in pence.
     */
    public function calculateMarriageGifts(string $relationship): int
    {
        $weddingGifts = $this->giftingConfig['wedding_gifts'];

        return match (strtolower($relationship)) {
            'child', 'son', 'daughter' => self::poundsToMinor($weddingGifts['child']),
            'grandchild', 'grandson', 'granddaughter', 'great_grandchild' => self::poundsToMinor($weddingGifts['grandchild_great_grandchild']),
            default => self::poundsToMinor($weddingGifts['other']),
        };
    }

    /**
     * Recommend optimal gifting strategy based on estate value and profile.
     *
     * @param  int  $estateValueMinor  Total estate value in pence.
     */
    public function recommendOptimalGiftingStrategy(int $estateValueMinor, IHTProfile $profile): array
    {
        $nrbMinor = self::poundsToMinor($this->ihtConfig['nil_rate_band']);
        $nrbFromSpouseMinor = self::poundsToMinor($profile->nrb_transferred_from_spouse ?? 0);
        $totalNrbMinor = $nrbMinor + $nrbFromSpouseMinor;

        $ihtRate = (float) $this->ihtConfig['standard_rate'];
        $reducedRate = (float) ($this->ihtConfig['reduced_rate_charity'] ?? 0.36);

        $taxableEstateMinor = max(0, $estateValueMinor - $totalNrbMinor);
        $currentIhtLiabilityMinor = (int) round($taxableEstateMinor * $ihtRate);

        $recommendations = [];
        $priority = [];

        $annualExemptionMinor = self::poundsToMinor($this->giftingConfig['annual_exemption']);
        $annualExemptionPounds = (int) round($annualExemptionMinor / 100);
        $recommendations[] = [
            'strategy' => 'Annual Exemption',
            'description' => "Gift up to £{$annualExemptionPounds} per year tax-free",
            'potential_savings_minor' => (int) round($annualExemptionMinor * $ihtRate),
        ];
        $priority[] = [
            'strategy' => 'Annual Exemption',
            'priority' => 1,
        ];

        $charitablePercent = (float) ($profile->charitable_giving_percent ?? 0);
        if ($charitablePercent < 10 && $currentIhtLiabilityMinor > 0) {
            $standardRatePercent = (int) round($ihtRate * 100);
            $reducedRatePercent = (int) round($reducedRate * 100);
            $recommendations[] = [
                'strategy' => 'Charitable Giving',
                'description' => "Leave 10% to charity to reduce IHT rate from {$standardRatePercent}% to {$reducedRatePercent}%",
                'potential_savings_minor' => (int) round($taxableEstateMinor * ($ihtRate - $reducedRate)),
            ];
            $priority[] = [
                'strategy' => 'Charitable Giving',
                'priority' => 2,
            ];
        }

        if ($currentIhtLiabilityMinor > 0) {
            $recommendations[] = [
                'strategy' => 'Potentially Exempt Transfers (PETs)',
                'description' => 'Make larger gifts that become exempt after 7 years',
                'potential_savings_minor' => $currentIhtLiabilityMinor,
            ];
            $priority[] = [
                'strategy' => 'Potentially Exempt Transfers',
                'priority' => 3,
            ];
        }

        return [
            'estate_value_minor' => $estateValueMinor,
            'nil_rate_band_minor' => $totalNrbMinor,
            'taxable_estate_minor' => $taxableEstateMinor,
            'current_iht_liability_minor' => $currentIhtLiabilityMinor,
            'recommendations' => $recommendations,
            'priority' => $priority,
        ];
    }

    private static function poundsToMinor(int|float|string|null $pounds): int
    {
        if ($pounds === null) {
            return 0;
        }

        return (int) round((float) $pounds * 100);
    }
}
