<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use InvalidArgumentException;

/**
 * Two-Pot contribution splitter (WS 1.4a).
 *
 * Contributions with date >= 2024-09-01 split:
 *   - 1/3 → Savings Component
 *   - 2/3 → Retirement Component
 *
 * Contributions with date < 2024-09-01 go 100% to the Vested Component.
 *
 * Integer division preserves the contribution total to the cent —
 * savings = intdiv(contribution, 3), retirement = contribution - savings.
 *
 * Pure calculator. Stateless. The caller applies returned deltas via
 * ZaRetirementFundBucketRepository::applyDeltas.
 */
class ZaContributionSplitService
{
    private const TWO_POT_EFFECTIVE_DATE = '2024-09-01';

    /**
     * @return array{vested_delta_minor: int, savings_delta_minor: int, retirement_delta_minor: int}
     */
    public function split(int $contributionMinor, string $contributionDate): array
    {
        if ($contributionMinor < 0) {
            throw new InvalidArgumentException('Contribution cannot be negative.');
        }

        if ($contributionDate < self::TWO_POT_EFFECTIVE_DATE) {
            return [
                'vested_delta_minor' => $contributionMinor,
                'savings_delta_minor' => 0,
                'retirement_delta_minor' => 0,
            ];
        }

        $savings = intdiv($contributionMinor, 3);
        $retirement = $contributionMinor - $savings;

        return [
            'vested_delta_minor' => 0,
            'savings_delta_minor' => $savings,
            'retirement_delta_minor' => $retirement,
        ];
    }
}
