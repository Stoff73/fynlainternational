<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Bank account and routing code validation contract for a jurisdiction.
 *
 * Each jurisdiction has its own account number format and routing/clearing
 * code scheme. This contract standardises validation and labelling.
 */
interface BankingValidator
{
    /**
     * Validate the format of a bank account number.
     *
     * @param string $accountNumber The bank account number to validate
     *
     * @return bool True if the account number matches the jurisdiction's expected format
     */
    public function validateAccountNumber(string $accountNumber): bool;

    /**
     * Validate the format of a bank routing/clearing code.
     *
     * @param string $code The routing code to validate
     *
     * @return bool True if the code matches the jurisdiction's expected format
     */
    public function validateRoutingCode(string $code): bool;

    /**
     * Get the local display label for the routing/clearing code.
     *
     * Different jurisdictions use different terms for the bank routing code
     * (e.g. "Sort Code", "Branch Code", "Routing Number", "BSB").
     *
     * @return string Human-readable label for the routing code field
     */
    public function getRoutingCodeLabel(): string;
}
