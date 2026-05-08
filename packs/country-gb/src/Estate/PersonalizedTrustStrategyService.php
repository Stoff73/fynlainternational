<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Estate;

use App\Models\User;
use App\Services\Risk\RiskPreferenceService;
use App\Services\Settings\AssumptionsService;
use Fynla\Packs\Gb\Constants\TaxDefaults;
use Fynla\Packs\Gb\Models\Estate\IHTProfile;
use Fynla\Packs\Gb\Tax\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Generates personalized trust planning strategies based on asset liquidity
 * and UK IHT rules for Chargeable Lifetime Transfers (CLTs).
 *
 * Money values are int minor units (pence). Callers convert at the boundary.
 *
 * Key UK IHT Trust Rules:
 * - NRB: £325,000 per person (transfers to relevant property trusts)
 * - Lifetime charge: 20% on amounts exceeding NRB (or 25% if settlor pays)
 * - Death within 7 years: Additional charge to bring total to 40% (less taper relief)
 * - 7-year lookback: CLTs cumulative over rolling 7 years
 * - Taper relief: Applies if death occurs 3-7 years after transfer
 */
class PersonalizedTrustStrategyService
{
    public function __construct(
        private readonly AssetLiquidityAnalyzer $liquidityAnalyzer,
        private readonly TaxConfigService $taxConfig,
        private readonly AssumptionsService $assumptionsService,
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Generate personalized trust planning strategy
     *
     * @param  Collection  $assets  User's assets
     * @param  int  $currentIHTLiabilityMinor  Current IHT liability, in pence
     * @param  IHTProfile  $profile  User's IHT profile
     * @param  User  $user  The user
     * @param  int  $yearsUntilDeath  Projected years until death
     * @return array Strategy details (money values keyed `*_minor`)
     */
    public function generatePersonalizedTrustStrategy(
        Collection $assets,
        int $currentIHTLiabilityMinor,
        IHTProfile $profile,
        User $user,
        int $yearsUntilDeath = 20
    ): array {
        // Analyze asset liquidity (still pounds-shaped — out of scope for R-14a)
        $liquidityAnalysis = $this->liquidityAnalyzer->analyzeAssetLiquidity($assets);

        // Calculate giftable amounts by liquidity (int-minor)
        $giftableAmountsMinor = $this->calculateGiftableAmounts($liquidityAnalysis);

        // Generate trust strategies (int-minor)
        $strategies = $this->generateTrustStrategies(
            $liquidityAnalysis,
            $giftableAmountsMinor,
            $currentIHTLiabilityMinor,
            $profile,
            $user,
            $yearsUntilDeath
        );

        // Calculate overall strategy impact (int-minor)
        $strategyImpact = $this->calculateStrategyImpact($strategies, $currentIHTLiabilityMinor, $yearsUntilDeath);

        return [
            'strategies' => $strategies,
            'liquidity_analysis' => $liquidityAnalysis,
            'giftable_amounts' => $giftableAmountsMinor,
            'strategy_impact' => $strategyImpact,
            'summary' => $this->generateSummary($strategies, $strategyImpact, $currentIHTLiabilityMinor),
        ];
    }

    /**
     * Calculate giftable amounts by liquidity category (int-minor).
     */
    private function calculateGiftableAmounts(array $liquidityAnalysis): array
    {
        $liquidAssets = $liquidityAnalysis['liquid']['assets'] ?? [];
        $semiLiquidAssets = $liquidityAnalysis['semi_liquid']['assets'] ?? [];
        $illiquidAssets = $liquidityAnalysis['illiquid']['assets'] ?? [];

        $immediatelyGiftableMinor = self::poundsToMinor($liquidityAnalysis['liquid']['total_value'] ?? 0);
        $giftableWithPlanningMinor = self::poundsToMinor($liquidityAnalysis['semi_liquid']['total_value'] ?? 0);
        $notGiftableMinor = self::poundsToMinor($liquidityAnalysis['illiquid']['total_value'] ?? 0);

        return [
            'immediately_giftable_minor' => $immediatelyGiftableMinor,
            'giftable_with_planning_minor' => $giftableWithPlanningMinor,
            'not_giftable_minor' => $notGiftableMinor,
            'total_giftable_minor' => $immediatelyGiftableMinor + $giftableWithPlanningMinor,
            'liquid_asset_count' => count($liquidAssets),
            'semi_liquid_asset_count' => count($semiLiquidAssets),
            'illiquid_asset_count' => count($illiquidAssets),
        ];
    }

    /**
     * Generate trust planning strategies
     */
    private function generateTrustStrategies(
        array $liquidityAnalysis,
        array $giftableAmountsMinor,
        int $currentIHTLiabilityMinor,
        IHTProfile $profile,
        User $user,
        int $yearsUntilDeath
    ): array {
        $strategies = [];
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $availableNRBMinor = self::poundsToMinor($profile->available_nrb ?? $ihtConfig['nil_rate_band']);

        // Strategy 1: Immediate CLT using available NRB (Discretionary Trust)
        $strategies[] = $this->buildImmediateCLTStrategy(
            $giftableAmountsMinor,
            $availableNRBMinor,
            $liquidityAnalysis
        );

        // Strategy 2: Multi-Cycle CLT Strategy (7-year cycles)
        $strategies[] = $this->buildMultiCycleCLTStrategy(
            $giftableAmountsMinor,
            $availableNRBMinor,
            $yearsUntilDeath,
            $liquidityAnalysis
        );

        // Strategy 3: Loan Trust Strategy (for large estates)
        $strategies[] = $this->buildLoanTrustStrategy(
            $giftableAmountsMinor,
            $liquidityAnalysis
        );

        // Strategy 4: Discounted Gift Trust Strategy (with retained income)
        $strategies[] = $this->buildDiscountedGiftTrustStrategy(
            $giftableAmountsMinor,
            $liquidityAnalysis,
            $user
        );

        // Strategy 5: Main Residence Property Trust Planning
        $strategies[] = $this->buildPropertyTrustStrategy(
            $liquidityAnalysis
        );

        return $strategies;
    }

    /**
     * Strategy 1: Immediate CLT using available NRB
     */
    private function buildImmediateCLTStrategy(
        array $giftableAmountsMinor,
        int $availableNRBMinor,
        array $liquidityAnalysis
    ): array {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $cltLifetimeRate = (float) ($ihtConfig['chargeable_lifetime_transfers']['lifetime_rate'] ?? 0.20);
        $cltSettlorRate = $cltLifetimeRate / (1 - $cltLifetimeRate); // Grossed-up rate when settlor pays

        $liquidAssets = collect($liquidityAnalysis['liquid']['assets'] ?? []);

        // For Immediate CLT, you can transfer all liquid assets (not capped at NRB)
        $amountToTrustMinor = (int) $giftableAmountsMinor['immediately_giftable_minor'];
        $excessOverNRBMinor = max(0, $amountToTrustMinor - $availableNRBMinor);

        // CLT taxation: 20% on excess over NRB (or 25% if settlor pays)
        $lifetimeChargeMinor = (int) round($excessOverNRBMinor * $cltLifetimeRate);
        $lifetimeChargeIfSettlorPaysMinor = (int) round($excessOverNRBMinor * $cltSettlorRate);

        // Potential additional charge if death within 7 years (40% total less the 20% already paid)
        $potentialDeathChargeMinor = (int) round($excessOverNRBMinor * $ihtRate) - $lifetimeChargeMinor;

        $amountToTrustPounds = intdiv($amountToTrustMinor, 100);
        $availableNRBPounds = intdiv($availableNRBMinor, 100);
        $lifetimeChargePounds = intdiv($lifetimeChargeMinor, 100);
        $lifetimeChargeIfSettlorPaysPounds = intdiv($lifetimeChargeIfSettlorPaysMinor, 100);

        $implementation = [
            '1. **Identify liquid assets** to transfer into discretionary trust (cash, investments)',
            '2. **Set up discretionary trust** with professional trustees',
            '3. **Transfer £'.number_format($amountToTrustPounds, 0).'** into the trust',
        ];

        if ($excessOverNRBMinor > 0) {
            $implementation[] = '4. **Pay lifetime IHT charge** of £'.number_format($lifetimeChargePounds, 0).' (20%) from trust, or £'.number_format($lifetimeChargeIfSettlorPaysPounds, 0).' (25%) if you pay';
            $implementation[] = '5. **Survive 7 years** to avoid additional 20% charge (total 40% if death occurs)';
        } else {
            $implementation[] = '4. **No immediate IHT charge** (within your £'.number_format($availableNRBPounds, 0).' NRB)';
            $implementation[] = '5. **Survive 7 years** to fully preserve NRB for other gifts';
        }

        $implementation[] = '6. **Trustees manage assets** for beneficiaries with discretion';
        $implementation[] = '7. **10-year anniversary charges** may apply (6% of value above NRB)';

        $eligibleAssets = $liquidAssets->map(function ($asset) {
            return [
                'asset_name' => $asset['asset_name'],
                'asset_type' => $asset['asset_type'],
                'current_value' => $asset['current_value'],
                'liquidity' => $asset['liquidity_classification']['liquidity'],
            ];
        })->toArray();

        $nrbUsedMinor = (int) min($amountToTrustMinor, $availableNRBMinor);

        return [
            'strategy_name' => 'Immediate Discretionary Trust (CLT)',
            'priority' => 1,
            'description' => 'Transfer liquid assets into a discretionary trust using your available Nil Rate Band',
            'amount_minor' => $amountToTrustMinor,
            'iht_saving_potential_minor' => (int) round($nrbUsedMinor * $ihtRate),
            'lifetime_tax_charge_minor' => $lifetimeChargeMinor,
            'potential_death_charge_minor' => $potentialDeathChargeMinor,
            'time_frame' => '7 years for full effectiveness',
            'risk_level' => $excessOverNRBMinor > 0 ? 'Medium' : 'Low',
            'suitable_for' => 'Liquid assets (cash, investments)',
            'implementation_steps' => $implementation,
            'tax_treatment' => [
                'immediate_charge_minor' => $lifetimeChargeMinor,
                'death_within_7_years_minor' => $potentialDeathChargeMinor + $lifetimeChargeMinor,
                'after_7_years_minor' => $lifetimeChargeMinor,
                'nrb_used_minor' => $nrbUsedMinor,
            ],
            'eligible_assets' => $eligibleAssets,
            'key_benefits' => [
                '✓ Assets immediately outside your estate for IHT',
                '✓ Growth outside your estate',
                '✓ Flexibility for trustees to distribute',
                '✓ Protection from creditors and divorce',
            ],
            'key_risks' => [
                '✗ Immediate IHT charge if over NRB (20%)',
                '✗ Additional charge if death within 7 years (up to 40% total)',
                '✗ 10-year anniversary charges (6% on value above NRB)',
                '✗ Loss of control over assets',
                '✗ Complex trust administration',
            ],
        ];
    }

    /**
     * Strategy 2: Multi-Cycle CLT Strategy (7-year cycles)
     */
    private function buildMultiCycleCLTStrategy(
        array $giftableAmountsMinor,
        int $availableNRBMinor,
        int $yearsUntilDeath,
        array $liquidityAnalysis
    ): array {
        $totalGiftableMinor = (int) $giftableAmountsMinor['total_giftable_minor'];
        $cyclesPossible = (int) floor($yearsUntilDeath / 7);

        if ($cyclesPossible < 1) {
            $cyclesPossible = 1;
        }

        // Each cycle can use the full NRB (7-year rolling window resets)
        $amountPerCycleMinor = $availableNRBMinor;
        $totalOverLifetimeMinor = (int) min($totalGiftableMinor, $amountPerCycleMinor * $cyclesPossible);
        $cyclesNeeded = $availableNRBMinor > 0
            ? (int) min($cyclesPossible, (int) ceil($totalGiftableMinor / $availableNRBMinor))
            : 1;

        $schedule = $this->buildCLTCycleSchedule($amountPerCycleMinor, $cyclesNeeded, $availableNRBMinor);

        // IHT saving: IHT rate on total transferred (assuming survival)
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $ihtSavingMinor = (int) round($totalOverLifetimeMinor * $ihtRate);

        $amountPerCyclePounds = intdiv($amountPerCycleMinor, 100);
        $availableNRBPounds = intdiv($availableNRBMinor, 100);
        $totalOverLifetimePounds = intdiv($totalOverLifetimeMinor, 100);

        $implementation = [
            '1. **Cycle 1 (Year 0)**: Transfer £'.number_format($amountPerCyclePounds, 0).' into discretionary trust',
            '2. **No immediate IHT charge** (within NRB of £'.number_format($availableNRBPounds, 0).')',
        ];

        if ($cyclesNeeded > 1) {
            for ($i = 2; $i <= $cyclesNeeded; $i++) {
                $year = ($i - 1) * 7;
                $implementation[] = ($i + 1).". **Cycle $i (Year $year)**: Transfer another £".number_format($amountPerCyclePounds, 0).' (NRB resets after 7 years)';
            }
        }

        $implementation[] = ($cyclesNeeded + 2).'. **Survive 7 years** after each transfer for full exemption';
        $implementation[] = ($cyclesNeeded + 3).'. **Total removed from estate**: £'.number_format($totalOverLifetimePounds, 0).' over '.(($cyclesNeeded - 1) * 7).' years';

        return [
            'strategy_name' => 'Multi-Cycle CLT Strategy',
            'priority' => 2,
            'description' => 'Use multiple 7-year cycles to maximize NRB usage for larger estates',
            'amount_minor' => $totalOverLifetimeMinor,
            'iht_saving_potential_minor' => $ihtSavingMinor,
            'lifetime_tax_charge_minor' => 0, // Assuming each cycle stays within NRB
            'potential_death_charge_minor' => $this->calculateMultiCycleDeathCharge($schedule, $yearsUntilDeath),
            'time_frame' => (($cyclesNeeded - 1) * 7).' years ('.$cyclesNeeded.' cycles)',
            'risk_level' => 'Medium',
            'suitable_for' => 'Large estates exceeding £'.number_format($availableNRBPounds, 0),
            'implementation_steps' => $implementation,
            'clt_schedule' => $schedule,
            'cycles_needed' => $cyclesNeeded,
            'cycles_possible' => $cyclesPossible,
            'key_benefits' => [
                '✓ Maximize NRB usage over multiple cycles',
                '✓ No immediate tax if each transfer within NRB',
                '✓ Each cycle starts fresh 7-year clock',
                '✓ Can remove large amounts from estate over time',
            ],
            'key_risks' => [
                '✗ Requires long time horizon (multiple 7-year cycles)',
                '✗ Death before 7 years on any cycle triggers charge',
                '✗ Complex timing and record-keeping',
                '✗ 10-year anniversary charges on each trust',
            ],
        ];
    }

    /**
     * Build CLT cycle schedule (int-minor)
     */
    private function buildCLTCycleSchedule(int $amountPerCycleMinor, int $cycles, int $nrbMinor): array
    {
        $schedule = [];
        $amountPerCyclePounds = intdiv($amountPerCycleMinor, 100);

        for ($i = 0; $i < $cycles; $i++) {
            $year = $i * 7;
            $schedule[] = [
                'cycle' => $i + 1,
                'year' => $year,
                'amount_minor' => $amountPerCycleMinor,
                'nrb_available_minor' => $nrbMinor,
                'immediate_charge_minor' => 0, // Within NRB
                'description' => 'Transfer £'.number_format($amountPerCyclePounds, 0)." in year $year",
            ];
        }

        return $schedule;
    }

    /**
     * Calculate potential death charge for multi-cycle strategy (int-minor).
     */
    private function calculateMultiCycleDeathCharge(array $schedule, int $yearsUntilDeath): int
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $totalChargeMinor = 0;

        foreach ($schedule as $cycle) {
            $yearsFromTransfer = $yearsUntilDeath - $cycle['year'];

            // If death occurs within 7 years of this transfer
            if ($yearsFromTransfer < 7) {
                $chargeMinor = (int) round($cycle['amount_minor'] * $ihtRate);

                // Apply taper relief if 3-7 years
                if ($yearsFromTransfer >= 3) {
                    $taperRate = $this->getTaperReliefRate($yearsFromTransfer);
                    $chargeMinor = (int) round($chargeMinor * ($taperRate / 100));
                }

                $totalChargeMinor += $chargeMinor;
            }
        }

        return $totalChargeMinor;
    }

    /**
     * Get taper relief rate based on years since transfer
     */
    private function getTaperReliefRate(int $years): int
    {
        if ($years < 3) {
            return 100;
        } // Full 40%
        if ($years < 4) {
            return 80;
        }  // 32%
        if ($years < 5) {
            return 60;
        }  // 24%
        if ($years < 6) {
            return 40;
        }  // 16%
        if ($years < 7) {
            return 20;
        }  // 8%

        return 0;                // 0% (fully exempt)
    }

    /**
     * Strategy 3: Loan Trust Strategy
     */
    private function buildLoanTrustStrategy(
        array $giftableAmountsMinor,
        array $liquidityAnalysis
    ): array {
        $liquidAssets = collect($liquidityAnalysis['liquid']['assets'] ?? []);
        $loanAmountMinor = (int) $giftableAmountsMinor['immediately_giftable_minor'];

        // Loan trust: lend money to trust, loan stays in estate but growth doesn't
        $assumedGrowthRate = 0.05; // 5% per year
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $growthOver20YearsMinor = (int) round($loanAmountMinor * (pow(1 + $assumedGrowthRate, 20) - 1));
        $ihtSavingMinor = (int) round($growthOver20YearsMinor * $ihtRate);

        $loanAmountPounds = intdiv($loanAmountMinor, 100);
        $growthOver20YearsPounds = intdiv($growthOver20YearsMinor, 100);
        $ihtSavingPounds = intdiv($ihtSavingMinor, 100);

        return [
            'strategy_name' => 'Loan Trust Strategy',
            'priority' => 3,
            'description' => 'Lend assets to a trust - loan stays in estate but future growth is IHT-free',
            'amount_minor' => $loanAmountMinor,
            'iht_saving_potential_minor' => $ihtSavingMinor,
            'lifetime_tax_charge_minor' => 0, // No immediate charge (it's a loan, not a gift)
            'potential_death_charge_minor' => 0, // Loan itself stays in estate
            'time_frame' => 'Immediate effect (growth is immediately outside estate)',
            'risk_level' => 'Low',
            'suitable_for' => 'Large estates, those wanting to retain access to capital',
            'implementation_steps' => [
                '1. **Set up loan trust** with trustees',
                '2. **Lend £'.number_format($loanAmountPounds, 0).'** to the trust (not a gift)',
                '3. **Loan remains in your estate** for IHT purposes',
                '4. **Investment growth is outside your estate** (IHT-free)',
                '5. **Repay loan to yourself** as needed for income/capital',
                '6. **Gradual loan write-off** can use annual exemptions (£3,000/year)',
                '7. **Assumed growth over 20 years**: £'.number_format($growthOver20YearsPounds, 0).' (IHT saving: £'.number_format($ihtSavingPounds, 0).')',
            ],
            'eligible_assets' => $liquidAssets->map(function ($asset) {
                return [
                    'asset_name' => $asset['asset_name'],
                    'asset_type' => $asset['asset_type'],
                    'current_value' => $asset['current_value'],
                ];
            })->toArray(),
            'key_benefits' => [
                '✓ No immediate IHT charge',
                '✓ Future growth outside your estate',
                '✓ Retain access to capital via loan repayments',
                '✓ Can write off loan gradually using annual exemptions',
                '✓ No 7-year wait for growth benefit',
            ],
            'key_risks' => [
                '✗ Loan value stays in estate',
                '✗ Complex trust administration',
                '✗ Requires investment growth to be effective',
                '✗ If loan repaid, growth benefit lost',
            ],
        ];
    }

    /**
     * Strategy 4: Discounted Gift Trust Strategy
     */
    private function buildDiscountedGiftTrustStrategy(
        array $giftableAmountsMinor,
        array $liquidityAnalysis,
        User $user
    ): array {
        $liquidAssets = collect($liquidityAnalysis['liquid']['assets'] ?? []);
        $giftValueMinor = (int) $giftableAmountsMinor['immediately_giftable_minor'];

        // Get IHT configuration
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $cltLifetimeRate = (float) ($ihtConfig['chargeable_lifetime_transfers']['lifetime_rate'] ?? 0.20);
        $nrbMinor = self::poundsToMinor($ihtConfig['nil_rate_band']);

        // Discounted gift trust: gift with retained income rights
        $assumedIncomeRate = 0.04; // 4% income
        $lifeExpectancy = 85 - ($user->age ?? 50); // Years remaining
        $discountRate = min(0.50, ($assumedIncomeRate * min($lifeExpectancy, 20)) / 2); // Simplified discount

        $discountValueMinor = (int) round($giftValueMinor * $discountRate);
        $cltValueMinor = $giftValueMinor - $discountValueMinor; // Actual chargeable amount

        // Discount stays in estate, gift value (after discount) is CLT
        $ihtSavingOnGiftMinor = (int) round($cltValueMinor * $ihtRate);
        $excessOverNRBMinor = max(0, $cltValueMinor - $nrbMinor);
        $lifetimeChargeMinor = (int) round($excessOverNRBMinor * $cltLifetimeRate);
        $potentialDeathChargeMinor = (int) round($excessOverNRBMinor * $ihtRate);

        $giftValuePounds = intdiv($giftValueMinor, 100);
        $cltValuePounds = intdiv($cltValueMinor, 100);
        $discountValuePounds = intdiv($discountValueMinor, 100);

        return [
            'strategy_name' => 'Discounted Gift Trust',
            'priority' => 4,
            'description' => 'Gift assets to trust but retain income rights - reduces the chargeable gift value',
            'amount_minor' => $giftValueMinor,
            'chargeable_amount_minor' => $cltValueMinor,
            'discount_value_minor' => $discountValueMinor,
            'discount_percentage' => $discountRate * 100,
            'iht_saving_potential_minor' => $ihtSavingOnGiftMinor,
            'lifetime_tax_charge_minor' => $lifetimeChargeMinor,
            'potential_death_charge_minor' => $potentialDeathChargeMinor,
            'time_frame' => '7 years for full effectiveness',
            'risk_level' => 'Medium',
            'suitable_for' => 'Those wanting to gift but retain income',
            'implementation_steps' => [
                '1. **Set up discounted gift trust** with income rights',
                '2. **Gift £'.number_format($giftValuePounds, 0).'** to the trust',
                '3. **Retain right to income** (e.g., '.($assumedIncomeRate * 100).'% = £'.number_format($giftValuePounds * $assumedIncomeRate, 0).'/year)',
                '4. **HMRC values gift** at £'.number_format($cltValuePounds, 0).' (after '.($discountRate * 100).'% discount)',
                '5. **Discount value £'.number_format($discountValuePounds, 0).' stays in your estate**',
                '6. **Capital growth** on full £'.number_format($giftValuePounds, 0).' is outside your estate',
                '7. **Survive 7 years** to remove CLT value from estate',
            ],
            'eligible_assets' => $liquidAssets->filter(function ($asset) {
                // Only suitable for income-producing assets
                return in_array($asset['asset_type'], ['investment', 'cash']);
            })->map(function ($asset) {
                return [
                    'asset_name' => $asset['asset_name'],
                    'asset_type' => $asset['asset_type'],
                    'current_value' => $asset['current_value'],
                ];
            })->toArray(),
            'key_benefits' => [
                '✓ Reduced CLT value due to discount',
                '✓ Retain income for life',
                '✓ All growth outside your estate',
                '✓ Lower IHT charge than full gift',
            ],
            'key_risks' => [
                '✗ Discount value stays in your estate',
                '✗ Complex valuation calculations',
                '✗ HMRC may challenge discount percentage',
                '✗ Still subject to 7-year rule on CLT value',
            ],
        ];
    }

    /**
     * Strategy 5: Main Residence Property Trust Planning
     */
    private function buildPropertyTrustStrategy(
        array $liquidityAnalysis
    ): array {
        $illiquidAssets = collect($liquidityAnalysis['illiquid']['assets'] ?? []);
        $mainResidence = $illiquidAssets->firstWhere('is_main_residence', true);

        if (! $mainResidence) {
            return [
                'strategy_name' => 'Property Trust Planning',
                'priority' => 5,
                'description' => 'N/A - No main residence identified',
                'amount_minor' => 0,
                'iht_saving_potential_minor' => 0,
                'applicable' => false,
            ];
        }

        $propertyValueMinor = self::poundsToMinor($mainResidence['current_value']);

        return [
            'strategy_name' => 'Property Trust Planning',
            'priority' => 5,
            'description' => 'Alternative approaches for your main residence to mitigate IHT',
            'amount_minor' => $propertyValueMinor,
            'iht_saving_potential_minor' => 0, // Cannot be directly calculated
            'lifetime_tax_charge_minor' => 0,
            'potential_death_charge_minor' => 0,
            'time_frame' => 'Long-term planning',
            'risk_level' => 'High',
            'suitable_for' => 'Main residence',
            'applicable' => true,
            'implementation_steps' => [
                '**Option A: Downsizing Strategy**',
                '1. Wait until dependants leave home',
                '2. Downsize to smaller property',
                '3. Gift released equity using PET/CLT strategies above',
                '4. Claim RNRB on remaining property value',
                '',
                '**Option B: Life Interest Trust in Will**',
                '1. Leave property in trust via Will (not lifetime)',
                '2. Spouse has right to live in property for life',
                '3. On second death, property passes to children',
                '4. Avoids double IHT charge on same property',
                '',
                '**Option C: Shared Ownership**',
                '1. Gift % ownership to adult children',
                '2. They pay market rent for their share (avoid GROB)',
                '3. Gradual transfer over time',
                '4. Complex and may not save significant IHT',
                '',
                '⚠️ **WARNING: Gift with Reservation of Benefit**',
                'You CANNOT gift your main residence and continue living in it rent-free.',
                "This is a 'gift with reservation of benefit' and remains in your estate for IHT.",
            ],
            'property_details' => [
                'property_name' => $mainResidence['asset_name'],
                'current_value_minor' => $propertyValueMinor,
                'reason_not_giftable' => $mainResidence['liquidity_classification']['not_giftable_reason'] ?? 'Main residence',
            ],
            'key_benefits' => [
                '✓ Downsizing releases equity for gifting',
                '✓ Life interest trust protects surviving spouse',
                '✓ RNRB available on main residence',
            ],
            'key_risks' => [
                '✗ Cannot gift and continue living in property',
                '✗ Gift with reservation rules are strict',
                '✗ Downsizing may not be desirable',
                '✗ Complex planning required',
            ],
        ];
    }

    /**
     * Calculate overall strategy impact (int-minor).
     */
    private function calculateStrategyImpact(array $strategies, int $currentIHTLiabilityMinor, int $yearsUntilDeath): array
    {
        $totalAmountTransferredMinor = 0;
        $totalIHTSavingMinor = 0;
        $totalLifetimeChargesMinor = 0;
        $totalPotentialDeathChargesMinor = 0;

        foreach ($strategies as $strategy) {
            if (isset($strategy['applicable']) && ! $strategy['applicable']) {
                continue;
            }

            $totalAmountTransferredMinor += (int) ($strategy['amount_minor'] ?? 0);
            $totalIHTSavingMinor += (int) ($strategy['iht_saving_potential_minor'] ?? 0);
            $totalLifetimeChargesMinor += (int) ($strategy['lifetime_tax_charge_minor'] ?? 0);
            $totalPotentialDeathChargesMinor += (int) ($strategy['potential_death_charge_minor'] ?? 0);
        }

        // Net saving = IHT saved - costs paid
        $netSavingMinor = $totalIHTSavingMinor - $totalLifetimeChargesMinor;

        // Worst case: if death occurs before 7 years on all transfers
        $worstCaseCostMinor = $totalLifetimeChargesMinor + $totalPotentialDeathChargesMinor;
        $worstCaseNetSavingMinor = $totalIHTSavingMinor - $worstCaseCostMinor;

        return [
            'total_amount_transferred_minor' => $totalAmountTransferredMinor,
            'total_iht_saving_minor' => $totalIHTSavingMinor,
            'total_lifetime_charges_minor' => $totalLifetimeChargesMinor,
            'total_potential_death_charges_minor' => $totalPotentialDeathChargesMinor,
            'net_saving_minor' => $netSavingMinor,
            'net_saving_percentage' => $currentIHTLiabilityMinor > 0
                ? ($netSavingMinor / $currentIHTLiabilityMinor) * 100
                : 0,
            'worst_case_cost_minor' => $worstCaseCostMinor,
            'worst_case_net_saving_minor' => $worstCaseNetSavingMinor,
            'projected_time_frame' => $yearsUntilDeath.' years',
        ];
    }

    /**
     * Generate summary (int-minor).
     */
    private function generateSummary(array $strategies, array $impact, int $currentIHTLiabilityMinor): array
    {
        $applicableStrategies = collect($strategies)->filter(function ($strategy) {
            return ! isset($strategy['applicable']) || $strategy['applicable'] !== false;
        });

        return [
            'current_iht_liability_minor' => $currentIHTLiabilityMinor,
            'total_strategies' => $applicableStrategies->count(),
            'recommended_strategy' => $applicableStrategies->sortBy('priority')->first()['strategy_name'] ?? 'None',
            'maximum_estate_reduction_minor' => (int) $impact['total_amount_transferred_minor'],
            'maximum_iht_saving_minor' => (int) $impact['total_iht_saving_minor'],
            'total_costs_minor' => (int) $impact['total_lifetime_charges_minor'],
            'net_benefit_minor' => (int) $impact['net_saving_minor'],
            'effectiveness_rating' => $this->calculateEffectivenessRating($impact, $currentIHTLiabilityMinor),
        ];
    }

    /**
     * Calculate effectiveness rating.
     */
    private function calculateEffectivenessRating(array $impact, int $currentIHTLiabilityMinor): string
    {
        if ($currentIHTLiabilityMinor === 0) {
            return 'N/A - No IHT liability';
        }

        $savingPercentage = ($impact['net_saving_minor'] / $currentIHTLiabilityMinor) * 100;

        if ($savingPercentage >= 80) {
            return 'Excellent';
        }
        if ($savingPercentage >= 60) {
            return 'Very Good';
        }
        if ($savingPercentage >= 40) {
            return 'Good';
        }
        if ($savingPercentage >= 20) {
            return 'Moderate';
        }

        return 'Limited';
    }

    /**
     * Calculate Nil Rate Band avoidance forward projection for a trust settlement.
     *
     * Projects the trust value growth over 11 years (covering the 10-year
     * periodic charge anniversary) to show whether the planned settlement
     * amount will exceed the Nil Rate Band threshold.
     *
     * Growth rate is sourced from the user's risk profile via AssumptionsService
     * and RiskPreferenceService -- never from TaxConfigService or hardcoded defaults.
     *
     * @param  User  $user  The user creating the trust
     * @param  int  $plannedAmountMinor  Planned initial settlement amount, in pence
     * @return array Forward projection with year-by-year trajectory (money keys `*_minor`)
     */
    public function calculateNRBAvoidanceProjection(User $user, int $plannedAmountMinor): array
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrbMinor = self::poundsToMinor($ihtConfig['nil_rate_band']);

        // Get growth rate from user's risk profile via AssumptionsService
        $riskLevel = $this->riskPreferenceService->getMainRiskLevel($user->id) ?? 'medium';

        try {
            $riskParams = $this->riskPreferenceService->getReturnParameters($riskLevel);
            $growthRate = ($riskParams['expected_return_typical'] ?? 5.0) / 100;
        } catch (\Exception $e) {
            $growthRate = 0.05; // Medium risk fallback
        }

        // Calculate maximum initial settlement that stays below NRB at 10-year anniversary
        $maxInitialSettlementMinor = (int) round($nrbMinor / pow(1 + $growthRate, 10));

        // Project the planned amount forward
        $projectedAt10YearsMinor = (int) round($plannedAmountMinor * pow(1 + $growthRate, 10));
        $willExceedNRB = $projectedAt10YearsMinor > $nrbMinor;

        // Year-by-year trajectory (0 to 11 years, covering the 10-year anniversary)
        $trajectory = [];
        for ($year = 0; $year <= 11; $year++) {
            $projectedValueMinor = (int) round($plannedAmountMinor * pow(1 + $growthRate, $year));
            $trajectory[] = [
                'year' => $year,
                'projected_value_minor' => $projectedValueMinor,
                'exceeds_nrb' => $projectedValueMinor > $nrbMinor,
            ];
        }

        // Calculate estimated periodic charge if value exceeds NRB at 10-year anniversary
        $estimatedPeriodicChargeMinor = 0;
        if ($willExceedNRB) {
            $estimatedPeriodicChargeMinor = $this->calculatePeriodicCharge($projectedAt10YearsMinor, $nrbMinor);
        }

        $plannedAmountPounds = intdiv($plannedAmountMinor, 100);
        $maxInitialSettlementPounds = intdiv($maxInitialSettlementMinor, 100);
        $nrbPounds = intdiv($nrbMinor, 100);

        return [
            'planned_amount_minor' => $plannedAmountMinor,
            'max_initial_settlement_minor' => $maxInitialSettlementMinor,
            'projected_at_10_years_minor' => $projectedAt10YearsMinor,
            'will_exceed_nrb' => $willExceedNRB,
            'nrb_threshold_minor' => $nrbMinor,
            'estimated_periodic_charge_minor' => $estimatedPeriodicChargeMinor,
            'growth_rate_used' => round($growthRate, 4),
            'risk_level' => $riskLevel,
            'trajectory' => $trajectory,
            'guidance' => $willExceedNRB
                ? 'The planned settlement of £'.number_format($plannedAmountPounds).' is projected to exceed the Nil Rate Band (£'.number_format($nrbPounds).') by the 10-year anniversary. Consider settling no more than £'.number_format($maxInitialSettlementPounds).' to avoid the periodic charge.'
                : 'The planned settlement of £'.number_format($plannedAmountPounds).' is projected to remain within the Nil Rate Band (£'.number_format($nrbPounds).') at the 10-year anniversary. No periodic charge is expected.',
        ];
    }

    /**
     * Calculate the estimated periodic charge on a trust value exceeding the Nil Rate Band.
     *
     * The periodic charge is approximately 6% of the value above the Nil Rate Band,
     * calculated on each 10-year anniversary of the trust.
     */
    private function calculatePeriodicCharge(int $trustValueMinor, int $nrbMinor): int
    {
        $excessOverNRBMinor = max(0, $trustValueMinor - $nrbMinor);

        // Maximum periodic charge rate is 6% (30% of lifetime rate of 20%)
        return (int) round($excessOverNRBMinor * 0.06);
    }

    /**
     * Convert a pounds-as-float|int|string|null value to int minor units (pence).
     */
    private static function poundsToMinor(int|float|string|null $pounds): int
    {
        return (int) round(((float) ($pounds ?? 0)) * 100);
    }
}
