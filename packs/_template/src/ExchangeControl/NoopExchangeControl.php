<?php

declare(strict_types=1);

namespace Fynla\Packs\XX\ExchangeControl;

use Fynla\Core\Contracts\ExchangeControl as ExchangeControlContract;

/**
 * Implements Fynla\Core\Contracts\ExchangeControl
 *
 * No-op implementation for countries without exchange control regulations.
 * Countries with exchange controls (e.g. South Africa) should replace this
 * with a full implementation covering allowances, approvals, and reporting.
 */
class NoopExchangeControl implements ExchangeControlContract
{
    /**
     * {@inheritDoc}
     */
    public function getAnnualAllowances(): array
    {
        // No exchange controls — return empty allowances
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function checkTransferPermitted(int $amountMinor, string $fromCurrency, string $toCurrency): bool
    {
        // No exchange controls — all transfers permitted
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllowanceConsumed(int $userId, string $period): int
    {
        // No exchange controls — nothing consumed
        return 0;
    }

    /**
     * {@inheritDoc}
     */
    public function requiresApproval(int $amountMinor, string $type): bool
    {
        // No exchange controls — no approval required
        return false;
    }
}
