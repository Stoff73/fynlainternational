<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Estate;

use App\Models\User;
use Fynla\Packs\Gb\Models\Estate\IHTProfile;
use Fynla\Packs\Gb\Tax\TaxConfigService;
use Fynla\Packs\Gb\Traits\ResolvesExpenditure;
use Fynla\Packs\Gb\Traits\ResolvesIncome;
use Illuminate\Support\Collection;

/**
 * Generates personalized gifting strategies based on user's actual assets and liquidity
 *
 * Unlike generic gifting advice, this service analyzes the user's specific assets
 * and provides tailored recommendations based on what they can actually gift.
 *
 * Money values are int minor units (pence). Callers convert at the boundary.
 */
class PersonalizedGiftingStrategyService
{
    use ResolvesExpenditure;
    use ResolvesIncome;

    public function __construct(
        private readonly AssetLiquidityAnalyzer $liquidityAnalyzer,
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Generate personalized gifting strategy based on user's assets
     *
     * @param  Collection  $assets  User's Estate Assets
     * @param  int  $currentIHTLiabilityMinor  Current IHT liability to reduce, in pence
     * @param  IHTProfile  $profile  User's IHT profile
     * @param  User  $user  User for income/expenditure data
     * @param  int  $yearsUntilDeath  Life expectancy in years
     * @return array Personalized gifting strategies (money values keyed `*_minor`)
     */
    public function generatePersonalizedStrategy(
        Collection $assets,
        int $currentIHTLiabilityMinor,
        IHTProfile $profile,
        User $user,
        int $yearsUntilDeath = 20
    ): array {
        // Analyze asset liquidity (still pounds-shaped — out of scope for R-14a)
        $liquidityAnalysis = $this->liquidityAnalyzer->analyzeAssetLiquidity($assets);
        $giftableAmounts = $this->liquidityAnalyzer->calculateMaximumGiftableAmount($assets);

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $giftingConfig = $this->taxConfig->getGiftingExemptions();
        $ihtRate = (float) $ihtConfig['standard_rate']; // 0.40
        $annualExemptionMinor = self::poundsToMinor($giftingConfig['annual_exemption']); // £3,000 → 300_000

        $strategies = [];
        $remainingIHTLiabilityMinor = $currentIHTLiabilityMinor;

        // If no IHT liability, no strategies needed
        if ($currentIHTLiabilityMinor <= 0) {
            return [
                'strategies' => [],
                'liquidity_analysis' => $liquidityAnalysis,
                'giftable_amounts' => $giftableAmounts,
                'summary' => [
                    'message' => 'No IHT liability - no gifting strategies needed',
                    'original_iht_liability_minor' => 0,
                    'total_iht_saved_minor' => 0,
                    'remaining_iht_liability_minor' => 0,
                    'total_gifted_minor' => 0,
                    'reduction_percentage' => 0,
                    'implementation_timeframe' => $yearsUntilDeath.' years',
                ],
            ];
        }

        // 1. Annual Exemption (always available)
        $annualExemptionStrategy = $this->buildAnnualExemptionStrategy($yearsUntilDeath, $annualExemptionMinor, $ihtRate);
        $strategies[] = $annualExemptionStrategy;
        $remainingIHTLiabilityMinor -= $annualExemptionStrategy['iht_saved_minor'];

        // 2. Asset-specific gifting strategies based on liquidity
        if ($giftableAmounts['immediately_giftable'] > 0) {
            $liquidAssetStrategy = $this->buildLiquidAssetGiftingStrategy(
                $liquidityAnalysis['liquid'],
                $remainingIHTLiabilityMinor,
                $ihtRate,
                $yearsUntilDeath
            );

            if ($liquidAssetStrategy) {
                $strategies[] = $liquidAssetStrategy;
                $remainingIHTLiabilityMinor -= $liquidAssetStrategy['iht_saved_minor'];
            }
        }

        // 3. Semi-liquid asset strategies (properties)
        if ($giftableAmounts['giftable_with_planning'] > 0 && $remainingIHTLiabilityMinor > 0) {
            $propertyStrategy = $this->buildPropertyGiftingStrategy(
                $liquidityAnalysis['semi_liquid'],
                $remainingIHTLiabilityMinor,
                $ihtRate
            );

            if ($propertyStrategy) {
                $strategies[] = $propertyStrategy;
                $remainingIHTLiabilityMinor -= $propertyStrategy['iht_saved_minor'];
            }
        }

        // 4. Illiquid asset strategies (main residence)
        if ($liquidityAnalysis['illiquid']['count'] > 0) {
            $mainResidenceStrategy = $this->buildMainResidenceStrategy(
                $liquidityAnalysis['illiquid'],
                $user
            );

            if ($mainResidenceStrategy) {
                $strategies[] = $mainResidenceStrategy;
            }
        }

        // Calculate totals (int-minor)
        $totalIHTSavedMinor = array_sum(array_column(array_filter($strategies, fn ($s) => isset($s['iht_saved_minor'])), 'iht_saved_minor'));
        $totalGiftedMinor = array_sum(array_column(array_filter($strategies, fn ($s) => isset($s['total_gifted_minor'])), 'total_gifted_minor'));

        return [
            'strategies' => $strategies,
            'liquidity_analysis' => $liquidityAnalysis,
            'giftable_amounts' => $giftableAmounts,
            'summary' => [
                'original_iht_liability_minor' => $currentIHTLiabilityMinor,
                'total_iht_saved_minor' => $totalIHTSavedMinor,
                'remaining_iht_liability_minor' => max(0, $currentIHTLiabilityMinor - $totalIHTSavedMinor),
                'total_gifted_minor' => $totalGiftedMinor,
                'reduction_percentage' => $currentIHTLiabilityMinor > 0
                    ? round(($totalIHTSavedMinor / $currentIHTLiabilityMinor) * 100, 1)
                    : 0,
                'implementation_timeframe' => $yearsUntilDeath.' years',
            ],
        ];
    }

    /**
     * Build annual exemption strategy
     */
    private function buildAnnualExemptionStrategy(int $years, int $annualExemptionMinor, float $ihtRate): array
    {
        $totalGiftedMinor = $annualExemptionMinor * $years;
        $ihtSavedMinor = (int) round($totalGiftedMinor * $ihtRate);
        $annualExemptionPounds = intdiv($annualExemptionMinor, 100);

        return [
            'strategy_name' => 'Annual Exemption',
            'priority' => 1,
            'category' => 'immediate_exemption',
            'description' => 'Gift £'.number_format($annualExemptionPounds, 0).' per year using annual exemption',
            'total_gifted_minor' => $totalGiftedMinor,
            'annual_amount_minor' => $annualExemptionMinor,
            'years' => $years,
            'iht_saved_minor' => $ihtSavedMinor,
            'risk_level' => 'Low',
            'implementation_steps' => [
                'Set up standing order for £'.number_format($annualExemptionPounds, 0).' per year to beneficiaries',
                'Immediately exempt - no 7-year wait',
                'Can carry forward unused allowance from previous year (one year only)',
                'Both spouses can gift £3,000 each (£6,000 total per year)',
                'Keep records of all gifts',
            ],
        ];
    }

    /**
     * Build liquid asset gifting strategy (cash, investments)
     */
    private function buildLiquidAssetGiftingStrategy(
        array $liquidAssets,
        int $remainingIHTLiabilityMinor,
        float $ihtRate,
        int $yearsUntilDeath
    ): ?array {
        if ($liquidAssets['count'] === 0 || $remainingIHTLiabilityMinor <= 0) {
            return null;
        }

        $liquidTotalMinor = self::poundsToMinor($liquidAssets['total_value']);

        // Calculate target gift amount to reduce IHT
        $targetGiftAmountMinor = (int) min($remainingIHTLiabilityMinor / $ihtRate, $liquidTotalMinor);

        // If user has 7+ years, can do PET strategy
        $complete7YearCycles = (int) floor($yearsUntilDeath / 7);

        if ($complete7YearCycles > 0) {
            // PET strategy - gift in 7-year cycles
            $amountPerCycleMinor = (int) min(intdiv($targetGiftAmountMinor, $complete7YearCycles), $liquidTotalMinor);
            $totalGiftedMinor = $amountPerCycleMinor * $complete7YearCycles;
            $ihtSavedMinor = (int) round($totalGiftedMinor * $ihtRate);

            $assetNames = implode(', ', array_column($liquidAssets['assets'], 'name'));
            $amountPerCyclePounds = intdiv($amountPerCycleMinor, 100);

            return [
                'strategy_name' => 'Gift Liquid Assets (Investments/Cash)',
                'priority' => 2,
                'category' => 'liquid_assets',
                'description' => 'Gift liquid assets (investments, cash) using Potentially Exempt Transfers (PETs)',
                'available_assets' => $assetNames,
                'total_available_minor' => $liquidTotalMinor,
                'recommended_gift_amount_minor' => $totalGiftedMinor,
                'total_gifted_minor' => $totalGiftedMinor,
                'gift_schedule' => $this->buildPETSchedule($amountPerCycleMinor, $complete7YearCycles),
                'iht_saved_minor' => $ihtSavedMinor,
                'risk_level' => 'Medium',
                'implementation_steps' => [
                    'Gift £'.number_format($amountPerCyclePounds, 0).' every 7 years from liquid assets',
                    'You have '.$complete7YearCycles.' complete 7-year cycle(s) before expected life expectancy',
                    'Gifts become fully exempt after 7 years',
                    'Taper relief applies from year 3 if you die within 7 years',
                    'Consider Capital Gains Tax (CGT) on investments before gifting',
                    'ISAs lose tax-free status when gifted',
                    'Specific assets to consider: '.$assetNames,
                ],
                'asset_details' => $liquidAssets['assets'],
            ];
        } else {
            // Not enough time for PET - immediate gift with risk
            $ihtSavedMinor = (int) round($targetGiftAmountMinor * $ihtRate);
            $targetGiftAmountPounds = intdiv($targetGiftAmountMinor, 100);

            return [
                'strategy_name' => 'Immediate Gift of Liquid Assets',
                'priority' => 2,
                'category' => 'liquid_assets',
                'description' => 'Gift liquid assets now (becomes PET - may not become fully exempt)',
                'total_available_minor' => $liquidTotalMinor,
                'recommended_gift_amount_minor' => $targetGiftAmountMinor,
                'total_gifted_minor' => $targetGiftAmountMinor,
                'iht_saved_minor' => $ihtSavedMinor,
                'risk_level' => 'High',
                'implementation_steps' => [
                    'Gift £'.number_format($targetGiftAmountPounds, 0).' immediately from liquid assets',
                    'WARNING: May not survive 7 years for full exemption',
                    'Taper relief may apply if you survive 3+ years',
                    'Consider gifting smaller amounts over time instead',
                ],
                'asset_details' => $liquidAssets['assets'],
            ];
        }
    }

    /**
     * Build property gifting strategy
     */
    private function buildPropertyGiftingStrategy(
        array $semiLiquidAssets,
        int $remainingIHTLiabilityMinor,
        float $ihtRate
    ): ?array {
        if ($semiLiquidAssets['count'] === 0 || $remainingIHTLiabilityMinor <= 0) {
            return null;
        }

        $propertyAssets = array_filter($semiLiquidAssets['assets'], fn ($a) => ($a['type'] ?? null) === 'property');

        if (empty($propertyAssets)) {
            return null;
        }

        $totalPropertyValueMinor = self::poundsToMinor(array_sum(array_column($propertyAssets, 'value')));
        $targetGiftAmountMinor = (int) min($remainingIHTLiabilityMinor / $ihtRate, $totalPropertyValueMinor);
        $ihtSavedMinor = (int) round($targetGiftAmountMinor * $ihtRate);

        $propertyNames = implode(', ', array_column($propertyAssets, 'name'));

        return [
            'strategy_name' => 'Gift Rental/Second Properties',
            'priority' => 3,
            'category' => 'property',
            'description' => 'Gift rental or second properties (not main residence)',
            'available_properties' => $propertyNames,
            'total_property_value_minor' => $totalPropertyValueMinor,
            'recommended_gift_amount_minor' => $targetGiftAmountMinor,
            'total_gifted_minor' => $targetGiftAmountMinor,
            'iht_saved_minor' => $ihtSavedMinor,
            'risk_level' => 'Medium',
            'implementation_steps' => [
                'Properties can be gifted outright or in stages (e.g., 10% per year)',
                'Consider Capital Gains Tax (CGT) on disposal (24% for residential property)',
                'Stamp Duty Land Tax (SDLT) may apply to recipient',
                'Gift becomes PET - exempt after 7 years',
                'Consider setting up a trust for flexibility',
                'Rental income should continue to recipient after gift',
                'Professional legal and tax advice essential',
                'Properties available: '.$propertyNames,
            ],
            'tax_considerations' => [
                'cgt_rate' => '24% on gains (residential property)',
                'sdlt' => 'May apply to recipient (varies by property value)',
                'iht_treatment' => 'PET - exempt after 7 years',
            ],
            'property_details' => $propertyAssets,
        ];
    }

    /**
     * Build main residence strategy (downsizing)
     */
    private function buildMainResidenceStrategy(array $illiquidAssets, User $user): ?array
    {
        $mainResidenceAssets = array_filter($illiquidAssets['assets'], fn ($a) => ! $a['is_giftable']);

        if (empty($mainResidenceAssets)) {
            return null;
        }

        $mainResidence = $mainResidenceAssets[0]; // Assume first is main residence
        $currentValueMinor = self::poundsToMinor($mainResidence['current_value']);
        $currentValuePounds = intdiv($currentValueMinor, 100);
        $downsizeTargetPounds = intdiv((int) round($currentValueMinor * 0.6), 100);
        $releasedEquityPounds = intdiv((int) round($currentValueMinor * 0.4), 100);
        $rnrbPounds = (int) ($this->taxConfig->getInheritanceTax()['residence_nil_rate_band'] ?? 0);

        return [
            'strategy_name' => 'Main Residence Strategy - Downsize to Release Equity',
            'priority' => 4,
            'category' => 'main_residence',
            'description' => 'Your main residence cannot be gifted while you live in it - consider downsizing',
            'main_residence' => $mainResidence['asset_name'],
            'current_value_minor' => $currentValueMinor,
            'total_gifted_minor' => 0, // Not directly gifted
            'iht_saved_minor' => 0, // Variable depending on downsizing
            'risk_level' => 'Low',
            'implementation_steps' => [
                'Cannot gift main residence while living in it (gift with reservation of benefit)',
                'Strategy: Downsize once dependants leave home',
                'Example: Sell £'.number_format($currentValuePounds, 0).' home, buy £'.number_format($downsizeTargetPounds, 0).' property',
                'Released equity (£'.number_format($releasedEquityPounds, 0).') can then be gifted',
                'Downsizing benefits: Lower running costs, easier maintenance, equity for gifting',
                'Timing: Consider when children leave home or retirement',
            ],
            'alternative_strategies' => [
                'Move out and pay market rent to continue living there (complex, requires professional advice)',
                'Leave via will to take advantage of Residence Nil Rate Band (£'.number_format($rnrbPounds, 0).')',
                'Consider equity release schemes (but reduces estate value for beneficiaries)',
            ],
            'not_giftable_reason' => $mainResidence['not_giftable_reason'],
        ];
    }

    /**
     * Build PET schedule for 7-year cycles
     */
    private function buildPETSchedule(int $amountPerCycleMinor, int $cycles): array
    {
        $schedule = [];

        for ($i = 0; $i < $cycles; $i++) {
            $schedule[] = [
                'year' => $i * 7,
                'amount_minor' => $amountPerCycleMinor,
                'becomes_exempt' => ($i * 7) + 7,
            ];
        }

        return $schedule;
    }

    /**
     * Convert a pounds-as-float|int|string|null value to int minor units (pence).
     * Tolerates null and string inputs from Eloquent / TaxConfig.
     */
    private static function poundsToMinor(int|float|string|null $pounds): int
    {
        return (int) round(((float) ($pounds ?? 0)) * 100);
    }
}
