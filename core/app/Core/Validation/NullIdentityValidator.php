<?php

declare(strict_types=1);

namespace Fynla\Core\Validation;

use Fynla\Core\Contracts\IdentityValidator;

/**
 * Sentinel IdentityValidator used while a pack does not yet supply
 * a real one. validate() always returns false — packs must wire a
 * real validator before user identity capture is shown.
 */
final class NullIdentityValidator implements IdentityValidator
{
    public function validate(string $idNumber): bool
    {
        return false;
    }

    public function extractMetadata(string $idNumber): array
    {
        return [];
    }
}
