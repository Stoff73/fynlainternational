<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Query;

use Fynla\Core\Contracts\PackAssetRepository;
use Illuminate\Support\Collection;

/**
 * SA pack Null implementation of the cross-module asset query contract.
 *
 * Phase 1 SA does not surface portfolio-shaped assets through this
 * contract — TFSA / Endowment / RA / Living-annuity surfaces will land
 * via dedicated WS 1.2/1.3/1.4 feature workstreams that follow R-14b.
 *
 * The composite default in core walks every registered pack via
 * PackRegistry; without these explicit Null bindings the composite
 * would silently skip ZA — fine for today but it's cheaper to bind a
 * proper Null impl now so the contract is structurally symmetric with
 * GB and Phase 2 cross-border merges can swap in real implementations
 * model-by-model.
 */
final class ZaPackAssetRepository implements PackAssetRepository
{
    public function userAccounts(int $userId): Collection
    {
        return new Collection();
    }

    public function householdAssets(int $householdId): Collection
    {
        return new Collection();
    }
}
