<?php

declare(strict_types=1);

namespace Fynla\Core\Jurisdiction;

use Fynla\Core\Models\Jurisdiction;
use Fynla\Core\Models\User;
use Fynla\Core\Models\UserJurisdiction;
use RuntimeException;

/**
 * Single writer of a user's primary jurisdiction, so that no creation path
 * (signup, spouse-creation, preview seeding, backfill) ever leaves a user
 * without one. Idempotent: updates the existing primary row or inserts one.
 */
class AssignPrimaryJurisdiction
{
    public function assign(User $user, string $countryCode): UserJurisdiction
    {
        $jurisdiction = Jurisdiction::byCode($countryCode);

        if ($jurisdiction === null) {
            throw new RuntimeException(
                "Cannot assign jurisdiction: country code '{$countryCode}' is not seeded."
            );
        }

        return UserJurisdiction::updateOrCreate(
            ['user_id' => $user->id, 'is_primary' => true],
            [
                'jurisdiction_id' => $jurisdiction->id,
                'activated_at' => now(),
                'deactivated_at' => null,
            ],
        );
    }
}
