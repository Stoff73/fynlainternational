<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * National identity number validation contract for a jurisdiction.
 *
 * Validates the format and check digits of jurisdiction-specific identity
 * numbers and extracts any demographic metadata encoded in the number.
 */
interface IdentityValidator
{
    /**
     * Validate the format and check digit(s) of an identity number.
     *
     * @param string $idNumber The identity number to validate
     *
     * @return bool True if the number is structurally valid
     */
    public function validate(string $idNumber): bool;

    /**
     * Extract demographic metadata encoded in the identity number.
     *
     * Returns an array of fields derivable from the ID format. Not all
     * jurisdictions encode the same fields; keys that cannot be derived
     * should be omitted rather than guessed.
     *
     * @param string $idNumber A valid identity number
     *
     * @return array{
     *     date_of_birth?: string,
     *     gender?: string,
     *     citizenship?: string
     * } Extracted metadata (ISO 8601 date, "male"/"female", citizenship status)
     */
    public function extractMetadata(string $idNumber): array;
}
