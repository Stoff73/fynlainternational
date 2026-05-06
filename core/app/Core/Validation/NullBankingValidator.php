<?php

declare(strict_types=1);

namespace Fynla\Core\Validation;

use Fynla\Core\Contracts\BankingValidator;

/**
 * Sentinel BankingValidator used while a pack does not yet supply
 * a real one. Validation methods always return false; the routing
 * code label falls back to the generic "Routing code".
 */
final class NullBankingValidator implements BankingValidator
{
    public function validateAccountNumber(string $accountNumber): bool
    {
        return false;
    }

    public function validateRoutingCode(string $code): bool
    {
        return false;
    }

    public function getRoutingCodeLabel(): string
    {
        return 'Routing code';
    }
}
