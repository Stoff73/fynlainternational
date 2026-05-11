<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Query;

use Fynla\Core\Contracts\PackEstateRepository;
use Fynla\Packs\Gb\Models\Estate\Asset;
use Fynla\Packs\Gb\Models\Estate\Gift;
use Fynla\Packs\Gb\Models\Estate\IHTProfile;
use Fynla\Packs\Gb\Models\Estate\LastingPowerOfAttorney;
use Fynla\Packs\Gb\Models\Estate\Liability;
use Fynla\Packs\Gb\Models\Estate\Trust;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * GB pack implementation of the Estate query contract.
 *
 * Surfaces the 6 Estate-shaped relationships User currently exposes via
 * pack-namespaced `hasMany` literals (liabilities, trusts, IHT profile,
 * estate assets, gifts, LPAs) as typed Collections / nullable Model so
 * core User can hand cross-pack reads off to the contract.
 */
final class GbPackEstateRepository implements PackEstateRepository
{
    public function liabilitiesForUser(int $userId): Collection
    {
        return Liability::query()
            ->where('user_id', $userId)
            ->get();
    }

    public function trustsForUser(int $userId): Collection
    {
        return Trust::query()
            ->where('user_id', $userId)
            ->get();
    }

    public function ihtProfileForUser(int $userId): ?Model
    {
        return IHTProfile::query()
            ->where('user_id', $userId)
            ->first();
    }

    public function estateAssetsForUser(int $userId): Collection
    {
        return Asset::query()
            ->where('user_id', $userId)
            ->get();
    }

    public function giftsForUser(int $userId): Collection
    {
        return Gift::query()
            ->where('user_id', $userId)
            ->get();
    }

    public function lpasForUser(int $userId): Collection
    {
        return LastingPowerOfAttorney::query()
            ->where('user_id', $userId)
            ->get();
    }
}
