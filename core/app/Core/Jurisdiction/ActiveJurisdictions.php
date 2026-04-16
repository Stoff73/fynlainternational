<?php

declare(strict_types=1);

namespace Fynla\Core\Jurisdiction;

use Fynla\Core\Models\Jurisdiction as JurisdictionModel;
use Fynla\Core\Models\UserJurisdiction;

/**
 * Resolves the active jurisdictions for an authenticated user.
 *
 * Queries the user_jurisdictions pivot table and respects the
 * FYNLA_ACTIVE_PACKS env var for installation-level pack enabling.
 */
class ActiveJurisdictions
{
    /**
     * Get all jurisdictions active for a given user.
     *
     * A user may have multiple jurisdictions (e.g. a dual-resident or
     * someone with financial interests in more than one country).
     *
     * Filters by both user assignment AND installation-level pack enabling
     * (FYNLA_ACTIVE_PACKS env var).
     *
     * @param int $userId The authenticated user's ID
     *
     * @return array<int, Jurisdiction> List of active jurisdictions for the user
     */
    public function forUser(int $userId): array
    {
        $activePacks = $this->activePacks();

        $models = JurisdictionModel::query()
            ->whereHas('users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->where('active', true)
            ->get();

        $jurisdictions = [];

        foreach ($models as $model) {
            // Only include jurisdictions whose pack is enabled at installation level
            $packCode = 'country-' . strtolower($model->code);
            if (in_array(strtoupper($model->code), $activePacks, true) ||
                in_array($packCode, $this->activePacksRaw(), true)) {
                $jurisdictions[] = $model->toValueObject();
            }
        }

        // Fallback for Phase 0: if no DB assignments found but user exists,
        // return GB if it's an active pack. This handles edge cases during
        // the transition before all users are fully backfilled.
        if (empty($jurisdictions) && in_array('GB', $activePacks, true)) {
            $gb = JurisdictionModel::byCode('GB');
            if ($gb !== null) {
                $jurisdictions[] = $gb->toValueObject();
            }
        }

        return $jurisdictions;
    }

    /**
     * Check whether a specific jurisdiction is active for a user.
     *
     * @param int    $userId The authenticated user's ID
     * @param string $code   ISO 3166-1 alpha-2 country code
     *
     * @return bool True if the user has the jurisdiction active
     */
    public function userHasJurisdiction(int $userId, string $code): bool
    {
        $jurisdictions = $this->forUser($userId);

        foreach ($jurisdictions as $jurisdiction) {
            if (strtoupper($jurisdiction->code) === strtoupper($code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the user's primary jurisdiction.
     *
     * @param int $userId The authenticated user's ID
     *
     * @return Jurisdiction|null The primary jurisdiction, or null if none set
     */
    public function primaryForUser(int $userId): ?Jurisdiction
    {
        $assignment = UserJurisdiction::query()
            ->where('user_id', $userId)
            ->where('is_primary', true)
            ->with('jurisdiction')
            ->first();

        if ($assignment === null || $assignment->jurisdiction === null) {
            // Fall back to first active jurisdiction
            $all = $this->forUser($userId);
            return $all[0] ?? null;
        }

        return $assignment->jurisdiction->toValueObject();
    }

    /**
     * Get the list of active pack codes from environment configuration.
     *
     * Reads the FYNLA_ACTIVE_PACKS env var (comma-separated pack identifiers).
     * Returns normalised uppercase country codes.
     *
     * @return array<int, string> List of active country codes (uppercase)
     */
    public function activePacks(): array
    {
        $raw = $this->activePacksRaw();

        return array_map(function (string $code): string {
            // Normalise "country-gb" → "GB", "GB" → "GB"
            $code = strtoupper(trim($code));
            if (str_starts_with($code, 'COUNTRY-')) {
                return substr($code, 8);
            }

            return $code;
        }, $raw);
    }

    /**
     * Get raw pack identifiers from env (before normalisation).
     *
     * @return array<int, string>
     */
    private function activePacksRaw(): array
    {
        // Use getenv() directly rather than Laravel's env() helper,
        // because env() reads from the cached config repository once
        // the app is booted and won't see putenv() changes at runtime.
        $packs = getenv('FYNLA_ACTIVE_PACKS');

        if ($packs === false || $packs === '') {
            return ['country-gb'];
        }

        return array_map('trim', explode(',', $packs));
    }
}
