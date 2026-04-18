<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Banking;

use Fynla\Core\Contracts\BankingValidator;

/**
 * SA banking validator — account number + universal branch code.
 *
 * SA account numbers are 7–11 digits (varies by bank). Universal branch
 * codes are 6 digits — the "universal code" standard replaced bank-
 * specific codes in most transactional flows.
 */
class ZaBankingValidator implements BankingValidator
{
    public function validateAccountNumber(string $accountNumber): bool
    {
        $clean = preg_replace('/\s+/', '', $accountNumber) ?? '';

        return (bool) preg_match('/^\d{7,11}$/', $clean);
    }

    public function validateRoutingCode(string $code): bool
    {
        $clean = preg_replace('/\s+/', '', $code) ?? '';

        return (bool) preg_match('/^\d{6}$/', $clean);
    }

    public function getRoutingCodeLabel(): string
    {
        return 'Branch Code';
    }
}
