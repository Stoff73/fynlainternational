<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Foreign exchange control contract for a jurisdiction.
 *
 * Some jurisdictions impose limits on cross-border transfers and
 * foreign currency purchases. For jurisdictions with no exchange
 * controls, implementations should return permissive defaults.
 * All monetary values are in minor currency units.
 */
interface ExchangeControl
{
    /**
     * Get annual foreign exchange allowances for the jurisdiction.
     *
     * @return array<string, array{
     *     type: string,
     *     annual_limit: int,
     *     currency: string,
     *     description: string
     * }> Named allowances with limits in minor currency units
     */
    public function getAnnualAllowances(): array;

    /**
     * Check whether a specific transfer is permitted under exchange control rules.
     *
     * @param int    $amountMinor  Transfer amount in minor currency units
     * @param string $fromCurrency ISO 4217 source currency code
     * @param string $toCurrency   ISO 4217 target currency code
     *
     * @return bool True if the transfer is permitted without special approval
     */
    public function checkTransferPermitted(int $amountMinor, string $fromCurrency, string $toCurrency): bool;

    /**
     * Get the total exchange allowance already consumed by a user in a period.
     *
     * @param int    $userId User identifier
     * @param string $period Period identifier (e.g. "2025", "2025/26")
     *
     * @return int Amount consumed in minor currency units
     */
    public function getAllowanceConsumed(int $userId, string $period): int;

    /**
     * Determine whether a transfer requires regulatory approval.
     *
     * @param int    $amountMinor Transfer amount in minor currency units
     * @param string $type        Transfer type (e.g. "investment", "emigration", "gift")
     *
     * @return bool True if prior approval from a regulatory body is required
     */
    public function requiresApproval(int $amountMinor, string $type): bool;
}
