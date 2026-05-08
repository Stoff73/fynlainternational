<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Validation;

use Fynla\Core\Contracts\IdentityValidator;

/**
 * UK National Insurance number (NINO) validator.
 *
 * Format: AA 12 34 56 A
 *   - 2 prefix letters (excluded: D, F, I, Q, U, V in either position;
 *                       excluded second letter also: O;
 *                       excluded prefix pairs: BG, GB, KN, NK, NT, TN, ZZ)
 *   - 6 digits
 *   - 1 suffix letter: A, B, C, or D
 *
 * Whitespace is permitted between groups and is stripped before validation.
 *
 * NINOs encode no demographic data (unlike SA ID numbers), so
 * extractMetadata always returns an empty array.
 */
class NinoValidator implements IdentityValidator
{
    private const FORBIDDEN_PREFIX_PAIRS = ['BG', 'GB', 'KN', 'NK', 'NT', 'TN', 'ZZ'];

    private const FORBIDDEN_FIRST_LETTERS = ['D', 'F', 'I', 'Q', 'U', 'V'];

    private const FORBIDDEN_SECOND_LETTERS = ['D', 'F', 'I', 'O', 'Q', 'U', 'V'];

    private const VALID_SUFFIXES = ['A', 'B', 'C', 'D'];

    public function validate(string $idNumber): bool
    {
        $clean = strtoupper(preg_replace('/\s+/', '', $idNumber) ?? '');

        if (! preg_match('/^[A-Z]{2}\d{6}[A-Z]$/', $clean)) {
            return false;
        }

        $first = $clean[0];
        $second = $clean[1];
        $prefix = $first . $second;
        $suffix = $clean[8];

        if (in_array($first, self::FORBIDDEN_FIRST_LETTERS, true)) {
            return false;
        }

        if (in_array($second, self::FORBIDDEN_SECOND_LETTERS, true)) {
            return false;
        }

        if (in_array($prefix, self::FORBIDDEN_PREFIX_PAIRS, true)) {
            return false;
        }

        if (! in_array($suffix, self::VALID_SUFFIXES, true)) {
            return false;
        }

        return true;
    }

    public function extractMetadata(string $idNumber): array
    {
        return [];
    }
}
