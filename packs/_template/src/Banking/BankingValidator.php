<?php

declare(strict_types=1);

namespace Fynla\Packs\XX\Banking;

use Fynla\Core\Contracts\BankingValidator as BankingValidatorContract;

/**
 * Implements Fynla\Core\Contracts\BankingValidator
 *
 * Country-specific bank account validation including account number format,
 * routing/sort code validation, and bank identification.
 *
 * TODO: Implement all methods from the BankingValidator contract.
 */
class BankingValidator implements BankingValidatorContract
{
    /**
     * {@inheritDoc}
     */
    public function validateAccountNumber(string $accountNumber): bool
    {
        throw new \RuntimeException('Not implemented: BankingValidator::validateAccountNumber');
    }

    /**
     * {@inheritDoc}
     */
    public function validateRoutingCode(string $code): bool
    {
        throw new \RuntimeException('Not implemented: BankingValidator::validateRoutingCode');
    }

    /**
     * {@inheritDoc}
     */
    public function getRoutingCodeLabel(): string
    {
        throw new \RuntimeException('Not implemented: BankingValidator::getRoutingCodeLabel');
    }
}
