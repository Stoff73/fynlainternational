<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\ExchangeControl;

use Fynla\Packs\Za\Models\ZaExchangeControlEntry;
use InvalidArgumentException;

/**
 * Thin persistence for SA exchange control transfer events.
 *
 * Append-only. Keyed by (user_id, calendar_year, allowance_type) — the
 * calendar year is the axis, not the tax year. Writes amounts in minor
 * units (cents) and always in ZAR for v1; foreign-currency transfers
 * must be pre-translated by the caller.
 */
class ZaExchangeControlLedger
{
    private const VALID_ALLOWANCE_TYPES = ['sda', 'fia'];

    public function record(
        int $userId,
        int $calendarYear,
        string $allowanceType,
        int $amountMinor,
        string $transferDate,
        ?string $destinationCountry = null,
        ?string $purpose = null,
        ?string $authorisedDealer = null,
        ?string $recipientAccount = null,
        ?string $aitReference = null,
        ?array $aitDocuments = null,
        ?string $notes = null,
    ): int {
        if (! in_array($allowanceType, self::VALID_ALLOWANCE_TYPES, true)) {
            throw new InvalidArgumentException(
                "allowance_type must be 'sda' or 'fia'; got '{$allowanceType}'.",
            );
        }
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Transfer amount must be positive.');
        }
        if ($calendarYear < 1900 || $calendarYear > 2200) {
            throw new InvalidArgumentException("Calendar year {$calendarYear} out of range.");
        }

        $entry = ZaExchangeControlEntry::create([
            'user_id' => $userId,
            'calendar_year' => $calendarYear,
            'allowance_type' => $allowanceType,
            'amount_minor' => $amountMinor,
            'amount_ccy' => 'ZAR',
            'destination_country' => $destinationCountry,
            'purpose' => $purpose,
            'authorised_dealer' => $authorisedDealer,
            'recipient_account' => $recipientAccount,
            'ait_reference' => $aitReference,
            'ait_documents' => $aitDocuments,
            'transfer_date' => $transferDate,
            'notes' => $notes,
        ]);

        return (int) $entry->id;
    }

    public function sumConsumed(int $userId, int $calendarYear, string $allowanceType): int
    {
        if (! in_array($allowanceType, self::VALID_ALLOWANCE_TYPES, true)) {
            throw new InvalidArgumentException(
                "allowance_type must be 'sda' or 'fia'; got '{$allowanceType}'.",
            );
        }

        return (int) ZaExchangeControlEntry::query()
            ->where('user_id', $userId)
            ->where('calendar_year', $calendarYear)
            ->where('allowance_type', $allowanceType)
            ->sum('amount_minor');
    }

    public function sumConsumedTotal(int $userId, int $calendarYear): int
    {
        return (int) ZaExchangeControlEntry::query()
            ->where('user_id', $userId)
            ->where('calendar_year', $calendarYear)
            ->sum('amount_minor');
    }
}
