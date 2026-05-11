<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Query;

use Fynla\Core\Contracts\PackEstateRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * SA pack Null implementation of the Estate query contract.
 *
 * Phase 1 SA does not model Estate surfaces — there is no SA-side
 * IHT-equivalent (Estate Duty applies but is structurally separate
 * from the UK liabilities/trusts/gifts/LPAs shape captured here).
 *
 * Bound for structural symmetry; Phase 2 may revisit if SA Estate
 * models are added to the pack.
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
        return new Collection();
    }

    public function lpasForUser(int $userId): Collection
    {
        return new Collection();
    }
}
