<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Goals;

use Fynla\Packs\Za\Tax\ZaTaxConfigService;

/**
 * SA Goals & Life Events defaults service (spec § 11).
 *
 * Exposes SA-specific default values for goal calculators: bond deposit
 * norms, SA tertiary tuition, severance tax-free threshold. All values
 * sourced from `za_tax_configurations` for annual Budget-refresh
 * adjustability.
 */
class ZaGoalsDefaults
{
    public function __construct(
        private readonly ZaTaxConfigService $config,
    ) {
    }

    /**
     * @return array{deposit_pct: float, default_term_years: int}
     */
    public function getBondDefaults(string $taxYear): array
    {
        return [
            'deposit_pct' => (float) $this->config->get($taxYear, 'goals.bond.deposit_pct_bps', 1000) / 100.0,
            'default_term_years' => (int) $this->config->get($taxYear, 'goals.bond.default_term_years', 20),
        ];
    }

    /**
     * @return array{public_annual_minor: int, private_annual_minor: int}
     */
    public function getTuitionDefaults(string $taxYear): array
    {
        return [
            'public_annual_minor' => (int) $this->config->get($taxYear, 'goals.tuition.public_tertiary_annual_minor', 7_500_000),
            'private_annual_minor' => (int) $this->config->get($taxYear, 'goals.tuition.private_tertiary_annual_minor', 15_000_000),
        ];
    }

    public function getSeveranceTaxFreeThresholdMinor(string $taxYear): int
    {
        return (int) $this->config->get($taxYear, 'goals.severance.tax_free_threshold_minor', 50_000_000);
    }
}
