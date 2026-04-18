<?php

declare(strict_types=1);

namespace App\Services\ExchangeControl;

use Fynla\Core\Contracts\ExchangeControl;

/**
 * UK-side ExchangeControl implementation.
 *
 * The UK has no exchange control regime — all cross-border transfers are
 * permitted without limit or regulatory approval. This class satisfies
 * the contract with no-op behaviour, mirroring the
 * packs/_template/NoopExchangeControl reference implementation.
 */
class UkExchangeControl implements ExchangeControl
{
    public function getAnnualAllowances(): array
    {
        return [];
    }

    public function checkTransferPermitted(int $amountMinor, string $fromCurrency, string $toCurrency): bool
    {
        return true;
    }

    public function getAllowanceConsumed(int $userId, string $period): int
    {
        return 0;
    }

    public function requiresApproval(int $amountMinor, string $type): bool
    {
        return false;
    }
}
