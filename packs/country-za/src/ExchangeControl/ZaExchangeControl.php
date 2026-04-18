<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\ExchangeControl;

use Fynla\Core\Contracts\ExchangeControl;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;

/**
 * South Africa exchange control implementation.
 *
 * Caps (per calendar year):
 *   - SDA: R2,000,000 — any purpose, no SARS approval
 *   - FIA: R10,000,000 — requires AIT for offshore investment
 *   - Combined > R12,000,000: SARB special approval via authorised dealer
 *
 * Limits are stored as minor-unit integers under the `excon.*` keys in
 * `za_tax_configurations`. Despite the "tax" table name, excon lives
 * there because it shares the same year-scoped read-through cache and
 * dot-path key pattern — there is no separate pack_configurations table.
 *
 * This implementation delegates consumption queries to
 * ZaExchangeControlLedger. It is otherwise stateless.
 */
class ZaExchangeControl implements ExchangeControl
{
    private const CURRENT_TAX_YEAR = '2026/27';

    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaExchangeControlLedger $ledger,
    ) {
    }

    public function getAnnualAllowances(): array
    {
        return [
            'sda' => [
                'type' => 'sda',
                'annual_limit' => $this->sdaCapMinor(),
                'currency' => 'ZAR',
                'description' => 'Single Discretionary Allowance — any legal purpose, no SARS approval',
            ],
            'fia' => [
                'type' => 'fia',
                'annual_limit' => $this->fiaCapMinor(),
                'currency' => 'ZAR',
                'description' => 'Foreign Investment Allowance — requires SARS AIT',
            ],
        ];
    }

    public function checkTransferPermitted(int $amountMinor, string $fromCurrency, string $toCurrency): bool
    {
        // A transfer where both sides are non-ZAR is outside the regime
        // (SA resident moving between offshore accounts; no rand leaves
        // the country).
        if ($fromCurrency !== 'ZAR' && $toCurrency !== 'ZAR') {
            return true;
        }

        // Any single ZAR-involved transfer above the SARB combined
        // threshold needs special approval — not permitted without it.
        if ($amountMinor > $this->sarbThresholdMinor()) {
            return false;
        }

        return true;
    }

    public function getAllowanceConsumed(int $userId, string $period): int
    {
        $year = (int) $period;

        return $this->ledger->sumConsumedTotal($userId, $year);
    }

    public function requiresApproval(int $amountMinor, string $type): bool
    {
        // SARB special approval always required above the combined threshold.
        if ($amountMinor > $this->sarbThresholdMinor()) {
            return true;
        }

        // SDA covers amounts up to R2m for any purpose — no approval.
        if ($amountMinor <= $this->sdaCapMinor()) {
            return false;
        }

        // Above SDA, below SARB threshold → FIA territory, requires AIT.
        return true;
    }

    private function sdaCapMinor(): int
    {
        return (int) $this->config->get(self::CURRENT_TAX_YEAR, 'excon.sda_annual_limit_minor', 0);
    }

    private function fiaCapMinor(): int
    {
        return (int) $this->config->get(self::CURRENT_TAX_YEAR, 'excon.fia_annual_limit_minor', 0);
    }

    private function sarbThresholdMinor(): int
    {
        return (int) $this->config->get(self::CURRENT_TAX_YEAR, 'excon.sarb_special_approval_threshold_minor', 0);
    }
}
