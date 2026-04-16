<?php

declare(strict_types=1);

namespace Fynla\Packs\XX\Identity;

use Fynla\Core\Contracts\IdentityValidator as IdentityValidatorContract;

/**
 * Implements Fynla\Core\Contracts\IdentityValidator
 *
 * Country-specific national identity number/document validation including
 * format checks, checksum verification, and metadata extraction.
 *
 * TODO: Implement all methods from the IdentityValidator contract.
 */
class IdentityValidator implements IdentityValidatorContract
{
    /**
     * {@inheritDoc}
     */
    public function validate(string $idNumber): bool
    {
        throw new \RuntimeException('Not implemented: IdentityValidator::validate');
    }

    /**
     * {@inheritDoc}
     */
    public function extractMetadata(string $idNumber): array
    {
        throw new \RuntimeException('Not implemented: IdentityValidator::extractMetadata');
    }
}
