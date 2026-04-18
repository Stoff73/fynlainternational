<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Packs\Za\Models\ZaReg28Snapshot;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use InvalidArgumentException;

/**
 * SA Regulation 28 asset-class compliance monitor.
 *
 * Checks a retirement-fund allocation against the 7 asset-class limits
 * plus the single-entity exposure limit. Returns per-class compliance
 * flags + an overall `compliant` flag + the list of breached classes.
 *
 * Allocation input shape (percentages 0–100, not fractions):
 *   [
 *     'offshore' => 30.0,
 *     'equity' => 60.0,
 *     'property' => 5.0,
 *     'private_equity' => 2.0,
 *     'commodities' => 1.0,
 *     'hedge_funds' => 1.0,
 *     'other' => 1.0,
 *     'single_entity' => 15.0,  // largest single-entity exposure
 *   ]
 *
 * Missing keys are treated as zero. Does NOT validate that classes sum
 * to 100 — that is a separate concern handled by the allocation
 * aggregator upstream.
 */
class ZaReg28Monitor
{
    /**
     * Map of allocation-key → config-key.
     */
    private const LIMITS = [
        'offshore' => 'reg28.offshore_max_bps',
        'equity' => 'reg28.equity_max_bps',
        'property' => 'reg28.property_max_bps',
        'private_equity' => 'reg28.private_equity_max_bps',
        'commodities' => 'reg28.commodities_max_bps',
        'hedge_funds' => 'reg28.hedge_funds_max_bps',
        'other' => 'reg28.other_max_bps',
        'single_entity' => 'reg28.single_entity_max_bps',
    ];

    public function __construct(
        private readonly ZaTaxConfigService $config,
    ) {
    }

    /**
     * @param array<string, float> $allocation Percentages 0–100.
     *
     * @return array{
     *     compliant: bool,
     *     breaches: array<int, string>,
     *     per_class: array<string, array{actual_pct: float, limit_pct: float, compliant: bool}>
     * }
     */
    public function check(array $allocation, string $taxYear): array
    {
        foreach ($allocation as $value) {
            if (! is_numeric($value) || $value < 0) {
                throw new InvalidArgumentException('Allocation values must be non-negative numbers.');
            }
        }

        $perClass = [];
        $breaches = [];
        $compliant = true;

        foreach (self::LIMITS as $class => $configKey) {
            $limitBps = (int) $this->config->get($taxYear, $configKey, 0);
            $limitPct = (float) $limitBps / 100.0;
            $actual = (float) ($allocation[$class] ?? 0.0);
            $classCompliant = $actual <= $limitPct + 1e-9;

            $perClass[$class] = [
                'actual_pct' => $actual,
                'limit_pct' => $limitPct,
                'compliant' => $classCompliant,
            ];

            if (! $classCompliant) {
                $breaches[] = $class;
                $compliant = false;
            }
        }

        return [
            'compliant' => $compliant,
            'breaches' => $breaches,
            'per_class' => $perClass,
        ];
    }

    /**
     * Runs check() and persists the result as a za_reg28_snapshots row.
     */
    public function snapshot(
        int $userId,
        ?int $fundHoldingId,
        array $allocation,
        string $asAtDate,
        string $taxYear,
    ): ZaReg28Snapshot {
        $result = $this->check($allocation, $taxYear);

        return ZaReg28Snapshot::create([
            'user_id' => $userId,
            'fund_holding_id' => $fundHoldingId,
            'as_at_date' => $asAtDate,
            'allocation' => $allocation,
            'offshore_compliant' => $result['per_class']['offshore']['compliant'],
            'equity_compliant' => $result['per_class']['equity']['compliant'],
            'property_compliant' => $result['per_class']['property']['compliant'],
            'private_equity_compliant' => $result['per_class']['private_equity']['compliant'],
            'commodities_compliant' => $result['per_class']['commodities']['compliant'],
            'hedge_funds_compliant' => $result['per_class']['hedge_funds']['compliant'],
            'other_compliant' => $result['per_class']['other']['compliant'],
            'single_entity_compliant' => $result['per_class']['single_entity']['compliant'],
            'compliant' => $result['compliant'],
            'breaches' => $result['breaches'],
        ]);
    }
}
