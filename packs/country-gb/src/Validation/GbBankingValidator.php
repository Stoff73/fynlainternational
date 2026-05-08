<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Validation;

use Fynla\Core\Contracts\BankingValidator;

/**
 * UK banking validator — sort code + account number.
 *
 * Sort code: 6 digits, conventionally displayed in 3 hyphen-separated
 * pairs (e.g. "12-34-56"). Both forms are accepted; whitespace and
 * hyphens are stripped before validation.
 *
 * Account number: 8 digits. A few historical bank-specific schemes
 * use shorter numbers (e.g. Co-op 7 digits) padded with leading zeros
 * for BACS/Faster Payments — only the canonical 8-digit form is
 * accepted.
 */
class GbBankingValidator implements BankingValidator
{
    public function validateAccountNumber(string $accountNumber): bool
    {
        $clean = preg_replace('/[\s-]+/', '', $accountNumber) ?? '';

        return (bool) preg_match('/^\d{8}$/', $clean);
    }

    public function validateRoutingCode(string $code): bool
    {
        $clean = preg_replace('/[\s-]+/', '', $code) ?? '';

        return (bool) preg_match('/^\d{6}$/', $clean);
    }

    public function getRoutingCodeLabel(): string
    {
        return 'Sort Code';
    }
}
