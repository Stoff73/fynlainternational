<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Query;

use Fynla\Core\Contracts\PackEstateRepository;
use Fynla\Packs\Za\Models\ZaDonation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * SA estate query implementation (WS2).
 *
 * SA has estate duty (not IHT), a donations register (not PET gifts), and no
 * LPA. Only the donations register maps to this GB-shaped contract via
 * giftsForUser(); SA estate-duty valuation is computed by ZaEstateEngine, not
 * surfaced here. The IHT-profile / liability / trust / LPA / estate-asset
 * methods return empty honestly rather than faking UK concepts SA lacks.
 */
final class ZaPackEstateRepository implements PackEstateRepository
{
    public function liabilitiesForUser(int $userId): Collection
    {
        return new Collection();
    }

    public function trustsForUser(int $userId): Collection
    {
        return new Collection();
    }

    public function ihtProfileForUser(int $userId): ?Model
    {
        return null;
    }

    public function estateAssetsForUser(int $userId): Collection
    {
        return new Collection();
    }

    public function giftsForUser(int $userId): Collection
    {
        return ZaDonation::query()->where('user_id', $userId)->get();
    }

    public function lpasForUser(int $userId): Collection
    {
        return new Collection();
    }
}
