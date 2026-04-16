<?php

declare(strict_types=1);

namespace App\Services\Investment\Fees;

/**
 * Platform Comparator
 * Compares investment platform costs across UK providers
 *
 * Platforms Compared:
 * - Vanguard
 * - Hargreaves Lansdown (HL)
 * - AJ Bell
 * - Interactive Investor (II)
 * - Fidelity
 * - Charles Stanley Direct
 * - Bestinvest
 * - Halifax Share Dealing
 *
 * Considers:
 * - Platform fees (tiered/flat/capped)
 * - Dealing charges
 * - ISA/SIPP availability
 * - Fund range and quality
 */
class PlatformComparator
{
    /**
     * Compare platforms for a given portfolio value
     *
     * @param  float  $portfolioValue  Portfolio value
     * @param  string  $accountType  Account type (isa, sipp, gia)
     * @param  int  $tradesPerYear  Number of trades per year
     * @return array Platform comparison
     */
    public function comparePlatforms(
        float $portfolioValue,
        string $accountType = 'isa',
        int $tradesPerYear = 4
    ): array {
        $platforms = $this->getAllPlatforms();
        $comparisons = [];

        foreach ($platforms as $platform) {
            if (! $this->platformSupportsAccountType($platform['code'], $accountType)) {
                continue;
            }

            $cost = $this->calculateTotalAnnualCost(
                $platform,
                $portfolioValue,
                $accountType,
                $tradesPerYear
            );

            $comparisons[] = [
                'platform_name' => $platform['name'],
                'platform_code' => $platform['code'],
                'annual_cost' => round($cost, 2),
                'cost_percent' => round(($cost / $portfolioValue) * 100, 3),
                'fee_structure' => $platform['fee_structure'],
                'dealing_charge' => $platform['dealing_charge'],
                'pros' => $platform['pros'],
                'cons' => $platform['cons'],
                'best_for' => $platform['best_for'],
                'website' => $platform['website'],
            ];
        }

        // Sort by annual cost
        usort($comparisons, fn ($a, $b) => $a['annual_cost'] <=> $b['annual_cost']);

        return [
            'portfolio_value' => $portfolioValue,
            'account_type' => $accountType,
            'trades_per_year' => $tradesPerYear,
            'platforms' => $comparisons,
            'cheapest' => $comparisons[0] ?? null,
            'most_expensive' => end($comparisons) ?: null,
            'potential_savings' => isset($comparisons[0], $comparisons[count($comparisons) - 1])
                ? round($comparisons[count($comparisons) - 1]['annual_cost'] - $comparisons[0]['annual_cost'], 2)
                : 0,
            'recommendation' => $this->generateRecommendation($comparisons, $portfolioValue),
        ];
    }

    /**
     * Compare specific platforms
     *
     * @param  array  $platformCodes  Array of platform codes to compare
     * @param  float  $portfolioValue  Portfolio value
     * @param  string  $accountType  Account type
     * @param  int  $tradesPerYear  Trades per year
     * @return array Comparison
     */
    public function compareSpecificPlatforms(
        array $platformCodes,
        float $portfolioValue,
        string $accountType = 'isa',
        int $tradesPerYear = 4
    ): array {
        $allPlatforms = $this->getAllPlatforms();
        $comparisons = [];

        foreach ($platformCodes as $code) {
            $platform = collect($allPlatforms)->firstWhere('code', $code);

            if (! $platform) {
                continue;
            }

            $cost = $this->calculateTotalAnnualCost(
                $platform,
                $portfolioValue,
                $accountType,
                $tradesPerYear
            );

            $comparisons[] = [
                'platform_name' => $platform['name'],
                'platform_code' => $platform['code'],
                'annual_cost' => round($cost, 2),
                'cost_percent' => round(($cost / $portfolioValue) * 100, 3),
                'breakdown' => $this->getCostBreakdown($platform, $portfolioValue, $accountType, $tradesPerYear),
            ];
        }

        usort($comparisons, fn ($a, $b) => $a['annual_cost'] <=> $b['annual_cost']);

        return [
            'comparisons' => $comparisons,
            'winner' => $comparisons[0] ?? null,
        ];
    }

    /**
     * Get all UK investment platforms
     *
     * @return array Platforms
     */
    private function getAllPlatforms(): array
    {
        return [
            [
                'code' => 'vanguard',
                'name' => 'Vanguard Investor UK',
                'fee_structure' => 'Tiered percentage',
                'platform_fee_tiers' => [
                    [0, 250000, 0.0015],
                    [250000, PHP_FLOAT_MAX, 0.00375],
                ],
                'dealing_charge' => 0,
                'account_types' => ['isa', 'sipp', 'gia'],
                'pros' => ['Very low fees', 'Excellent fund range', 'Good for passive investors'],
                'cons' => ['Limited to Vanguard funds', 'No stocks/ETFs'],
                'best_for' => 'Passive index fund investors',
                'website' => 'https://www.vanguardinvestor.co.uk',
            ],
            [
                'code' => 'hargreaves_lansdown',
                'name' => 'Hargreaves Lansdown',
                'fee_structure' => 'Tiered percentage',
                'platform_fee_tiers' => [
                    [0, 250000, 0.0045],
                    [250000, 1000000, 0.0025],
                    [1000000, 2000000, 0.0010],
                    [2000000, PHP_FLOAT_MAX, 0.0],
                ],
                'dealing_charge' => 11.95,
                'account_types' => ['isa', 'sipp', 'gia', 'jisa', 'lifetime_isa'],
                'pros' => ['Huge fund range', 'Excellent research', 'Strong customer service'],
                'cons' => ['High fees', 'Expensive for active traders'],
                'best_for' => 'Investors wanting wide choice and research',
                'website' => 'https://www.hl.co.uk',
            ],
            [
                'code' => 'aj_bell',
                'name' => 'AJ Bell Youinvest',
                'fee_structure' => 'Capped percentage',
                'platform_fee_rate' => 0.0025,
                'platform_fee_min' => 3.50,
                'platform_fee_max' => 7.50,
                'dealing_charge' => 9.95,
                'account_types' => ['isa', 'sipp', 'gia', 'jisa'],
                'pros' => ['Competitive fees', 'Wide range', 'Good mobile app'],
                'cons' => ['Quarterly fee payment', 'Dealing charges add up'],
                'best_for' => 'Balanced investors with £100k-£500k',
                'website' => 'https://www.ajbell.co.uk',
            ],
            [
                'code' => 'interactive_investor',
                'name' => 'Interactive Investor',
                'fee_structure' => 'Flat monthly fee',
                'monthly_fee' => 9.99,
                'dealing_charge' => 3.99,
                'free_trades_per_month' => 1,
                'account_types' => ['isa', 'sipp', 'gia', 'jisa', 'lifetime_isa'],
                'pros' => ['Predictable costs', 'Best for active traders', 'Cheap dealing'],
                'cons' => ['High cost for small portfolios', 'Monthly fee regardless of balance'],
                'best_for' => 'Active traders or portfolios over £50k',
                'website' => 'https://www.ii.co.uk',
            ],
            [
                'code' => 'fidelity',
                'name' => 'Fidelity Personal Investing',
                'fee_structure' => 'Capped percentage',
                'platform_fee_rate' => 0.0035,
                'platform_fee_min' => 0,
                'platform_fee_max' => 45,
                'dealing_charge' => 10,
                'account_types' => ['isa', 'sipp', 'gia', 'jisa'],
                'pros' => ['Low fees for £100k+', 'Good fund range', 'Strong research'],
                'cons' => ['0.35% fee for portfolios under £250k', 'Higher than competitors'],
                'best_for' => 'Larger portfolios (£250k+)',
                'website' => 'https://www.fidelity.co.uk',
            ],
            [
                'code' => 'charles_stanley',
                'name' => 'Charles Stanley Direct',
                'fee_structure' => 'Tiered percentage',
                'platform_fee_tiers' => [
                    [0, 50000, 0.0025],
                    [50000, 500000, 0.0015],
                    [500000, PHP_FLOAT_MAX, 0.0010],
                ],
                'dealing_charge' => 11.50,
                'account_types' => ['isa', 'sipp', 'gia'],
                'pros' => ['Competitive tiered fees', 'Good for larger portfolios', 'Strong investment options'],
                'cons' => ['Limited research vs HL', 'Higher dealing charges'],
                'best_for' => 'Medium to large portfolios',
                'website' => 'https://www.charles-stanley-direct.co.uk',
            ],
            [
                'code' => 'bestinvest',
                'name' => 'Bestinvest',
                'fee_structure' => 'Tiered percentage',
                'platform_fee_tiers' => [
                    [0, 250000, 0.0040],
                    [250000, PHP_FLOAT_MAX, 0.0020],
                ],
                'dealing_charge' => 4.95,
                'account_types' => ['isa', 'sipp', 'gia', 'jisa'],
                'pros' => ['Low dealing charges', 'Good research', 'Strong model portfolios'],
                'cons' => ['Higher platform fee', 'Less well-known'],
                'best_for' => 'Active traders with diversified portfolio',
                'website' => 'https://www.bestinvest.co.uk',
            ],
        ];
    }

    /**
     * Calculate total annual cost for a platform
     *
     * @param  array  $platform  Platform details
     * @param  float  $portfolioValue  Portfolio value
     * @param  string  $accountType  Account type
     * @param  int  $tradesPerYear  Trades per year
     * @return float Total annual cost
     */
    private function calculateTotalAnnualCost(
        array $platform,
        float $portfolioValue,
        string $accountType,
        int $tradesPerYear
    ): float {
        // Platform fee
        $platformFee = $this->calculatePlatformFee($platform, $portfolioValue);

        // Dealing charges
        $dealingCost = $this->calculateDealingCost($platform, $tradesPerYear);

        return $platformFee + $dealingCost;
    }

    /**
     * Calculate platform fee
     *
     * @param  array  $platform  Platform
     * @param  float  $value  Portfolio value
     * @return float Annual platform fee
     */
    private function calculatePlatformFee(array $platform, float $value): float
    {
        if (isset($platform['monthly_fee'])) {
            // Flat monthly fee (e.g., Interactive Investor)
            return $platform['monthly_fee'] * 12;
        }

        if (isset($platform['platform_fee_tiers'])) {
            // Tiered fee structure
            return $this->calculateTieredFee($value, $platform['platform_fee_tiers']);
        }

        if (isset($platform['platform_fee_rate'])) {
            // Capped percentage fee
            $fee = $value * $platform['platform_fee_rate'];
            $min = $platform['platform_fee_min'] ?? 0;
            $max = $platform['platform_fee_max'] ?? PHP_FLOAT_MAX;

            return max($min * 12, min($fee, $max)); // AJ Bell charges quarterly, so multiply min by 12
        }

        return 0;
    }

    /**
     * Calculate tiered fee
     *
     * @param  float  $value  Portfolio value
     * @param  array  $tiers  Tiers
     * @return float Annual fee
     */
    private function calculateTieredFee(float $value, array $tiers): float
    {
        $totalFee = 0;

        foreach ($tiers as [$min, $max, $rate]) {
            if ($value <= $min) {
                break;
            }

            $tierValue = min($value, $max) - $min;
            $totalFee += $tierValue * $rate;

            if ($value <= $max) {
                break;
            }
        }

        return $totalFee;
    }

    /**
     * Calculate dealing cost
     *
     * @param  array  $platform  Platform
     * @param  int  $tradesPerYear  Trades per year
     * @return float Annual dealing cost
     */
    private function calculateDealingCost(array $platform, int $tradesPerYear): float
    {
        $dealingCharge = $platform['dealing_charge'] ?? 0;
        $freeTrades = $platform['free_trades_per_month'] ?? 0;

        $paidTrades = max(0, $tradesPerYear - ($freeTrades * 12));

        return $paidTrades * $dealingCharge;
    }

    /**
     * Check if platform supports account type
     *
     * @param  string  $platformCode  Platform code
     * @param  string  $accountType  Account type
     * @return bool Supports account type
     */
    private function platformSupportsAccountType(string $platformCode, string $accountType): bool
    {
        $platforms = $this->getAllPlatforms();
        $platform = collect($platforms)->firstWhere('code', $platformCode);

        if (! $platform) {
            return false;
        }

        return in_array($accountType, $platform['account_types']);
    }

    /**
     * Get detailed cost breakdown
     *
     * @param  array  $platform  Platform
     * @param  float  $portfolioValue  Portfolio value
     * @param  string  $accountType  Account type
     * @param  int  $tradesPerYear  Trades per year
     * @return array Cost breakdown
     */
    private function getCostBreakdown(
        array $platform,
        float $portfolioValue,
        string $accountType,
        int $tradesPerYear
    ): array {
        $platformFee = $this->calculatePlatformFee($platform, $portfolioValue);
        $dealingCost = $this->calculateDealingCost($platform, $tradesPerYear);
        $total = $platformFee + $dealingCost;

        return [
            'platform_fee' => round($platformFee, 2),
            'dealing_costs' => round($dealingCost, 2),
            'total' => round($total, 2),
            'platform_fee_percent' => $portfolioValue > 0 ? round(($platformFee / $portfolioValue) * 100, 3) : 0,
        ];
    }

    /**
     * Generate recommendation
     *
     * @param  array  $comparisons  Platform comparisons
     * @param  float  $portfolioValue  Portfolio value
     * @return array Recommendation
     */
    private function generateRecommendation(array $comparisons, float $portfolioValue): array
    {
        if (empty($comparisons)) {
            return [
                'platform' => null,
                'rationale' => 'No suitable platforms found',
            ];
        }

        $cheapest = $comparisons[0];

        // Special recommendations based on portfolio size and behavior
        if ($portfolioValue < 50000) {
            return [
                'platform' => $cheapest['platform_name'],
                'rationale' => sprintf(
                    'For portfolios under £50k, %s offers the best value at £%s/year (%.2f%%)',
                    $cheapest['platform_name'],
                    number_format($cheapest['annual_cost'], 2),
                    $cheapest['cost_percent']
                ),
            ];
        } elseif ($portfolioValue < 250000) {
            // Find Interactive Investor in comparisons
            $ii = collect($comparisons)->firstWhere('platform_code', 'interactive_investor');

            if ($ii && $ii['annual_cost'] < $cheapest['annual_cost'] * 1.1) {
                return [
                    'platform' => $ii['platform_name'],
                    'rationale' => sprintf(
                        'For portfolios £50k-£250k with regular trading, %s offers good value at £%s/year with low dealing charges',
                        $ii['platform_name'],
                        number_format($ii['annual_cost'], 2)
                    ),
                ];
            }
        }

        return [
            'platform' => $cheapest['platform_name'],
            'rationale' => sprintf(
                '%s provides the lowest cost at £%s/year (%.2f%% of portfolio)',
                $cheapest['platform_name'],
                number_format($cheapest['annual_cost'], 2),
                $cheapest['cost_percent']
            ),
        ];
    }
}
