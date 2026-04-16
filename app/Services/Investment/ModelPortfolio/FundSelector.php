<?php

declare(strict_types=1);

namespace App\Services\Investment\ModelPortfolio;

/**
 * Fund Selector
 * Recommends low-cost index funds for portfolio construction
 *
 * Focus: UK-available Vanguard index funds (lowest cost)
 * Categories: UK Equity, Global Equity, Emerging Markets, Bonds, Alternatives
 */
class FundSelector
{
    /**
     * Get fund recommendations for asset allocation
     *
     * @param  array  $assetAllocation  Target asset allocation
     * @return array Fund recommendations
     */
    public function getFundRecommendations(array $assetAllocation): array
    {
        $funds = [];

        // Equity funds
        if ($assetAllocation['equities'] > 0) {
            $funds = array_merge($funds, $this->selectEquityFunds($assetAllocation['equities']));
        }

        // Bond funds
        if ($assetAllocation['bonds'] > 0) {
            $funds = array_merge($funds, $this->selectBondFunds($assetAllocation['bonds']));
        }

        // Cash
        if ($assetAllocation['cash'] > 0) {
            $funds[] = [
                'name' => 'Cash/Money Market',
                'ticker' => 'CASH',
                'allocation' => $assetAllocation['cash'],
                'asset_class' => 'cash',
                'ocf' => 0.0000,
                'type' => 'Cash',
            ];
        }

        // Alternatives
        if ($assetAllocation['alternatives'] > 0) {
            $funds[] = [
                'name' => 'Vanguard UK Property',
                'ticker' => 'VUKP',
                'allocation' => $assetAllocation['alternatives'],
                'asset_class' => 'alternatives',
                'ocf' => 0.0015,
                'type' => 'Property',
            ];
        }

        return [
            'funds' => $funds,
            'total_ocf' => $this->calculateWeightedOCF($funds),
            'fund_count' => count($funds),
        ];
    }

    /**
     * Select equity funds
     *
     * @param  float  $equityPercent  Target equity percentage
     * @return array Equity fund selection
     */
    private function selectEquityFunds(float $equityPercent): array
    {
        // Standard allocation: 30% UK, 60% Developed ex-UK, 10% Emerging
        return [
            [
                'name' => 'Vanguard FTSE UK All Share Index',
                'ticker' => 'VUKE',
                'allocation' => round($equityPercent * 0.30, 1),
                'asset_class' => 'equities',
                'ocf' => 0.0006,
                'type' => 'UK Equity',
            ],
            [
                'name' => 'Vanguard FTSE Developed World ex-UK',
                'ticker' => 'VDWE',
                'allocation' => round($equityPercent * 0.60, 1),
                'asset_class' => 'equities',
                'ocf' => 0.0014,
                'type' => 'Global Equity',
            ],
            [
                'name' => 'Vanguard FTSE Emerging Markets',
                'ticker' => 'VFEM',
                'allocation' => round($equityPercent * 0.10, 1),
                'asset_class' => 'equities',
                'ocf' => 0.0023,
                'type' => 'Emerging Markets',
            ],
        ];
    }

    /**
     * Select bond funds
     *
     * @param  float  $bondPercent  Target bond percentage
     * @return array Bond fund selection
     */
    private function selectBondFunds(float $bondPercent): array
    {
        // Standard allocation: 60% UK Gilts, 40% Global Bonds
        return [
            [
                'name' => 'Vanguard UK Government Bond Index',
                'ticker' => 'VGOV',
                'allocation' => round($bondPercent * 0.60, 1),
                'asset_class' => 'bonds',
                'ocf' => 0.0012,
                'type' => 'UK Gilts',
            ],
            [
                'name' => 'Vanguard Global Bond Index',
                'ticker' => 'VGBF',
                'allocation' => round($bondPercent * 0.40, 1),
                'asset_class' => 'bonds',
                'ocf' => 0.0015,
                'type' => 'Global Bonds',
            ],
        ];
    }

    /**
     * Calculate weighted OCF
     *
     * @param  array  $funds  Fund list
     * @return float Weighted OCF
     */
    private function calculateWeightedOCF(array $funds): float
    {
        $weightedOCF = 0;

        foreach ($funds as $fund) {
            $weightedOCF += ($fund['allocation'] / 100) * $fund['ocf'];
        }

        return round($weightedOCF, 4);
    }

    /**
     * Get all available funds
     *
     * @return array All funds database
     */
    public function getAllFunds(): array
    {
        return [
            'uk_equity' => [
                ['name' => 'Vanguard FTSE UK All Share Index', 'ticker' => 'VUKE', 'ocf' => 0.0006],
                ['name' => 'Vanguard FTSE 100 Index', 'ticker' => 'VUKE', 'ocf' => 0.0006],
            ],
            'global_equity' => [
                ['name' => 'Vanguard FTSE Developed World ex-UK', 'ticker' => 'VDWE', 'ocf' => 0.0014],
                ['name' => 'Vanguard FTSE Global All Cap Index', 'ticker' => 'VGAG', 'ocf' => 0.0023],
                ['name' => 'Vanguard S&P 500', 'ticker' => 'VUSA', 'ocf' => 0.0007],
            ],
            'emerging_markets' => [
                ['name' => 'Vanguard FTSE Emerging Markets', 'ticker' => 'VFEM', 'ocf' => 0.0023],
            ],
            'bonds' => [
                ['name' => 'Vanguard UK Government Bond Index', 'ticker' => 'VGOV', 'ocf' => 0.0012],
                ['name' => 'Vanguard Global Bond Index', 'ticker' => 'VGBF', 'ocf' => 0.0015],
                ['name' => 'Vanguard UK Investment Grade Bond', 'ticker' => 'VRIG', 'ocf' => 0.0012],
            ],
            'alternatives' => [
                ['name' => 'Vanguard UK Property', 'ticker' => 'VUKP', 'ocf' => 0.0015],
            ],
        ];
    }
}
