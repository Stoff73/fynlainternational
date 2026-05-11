<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Query;

use Fynla\Core\Contracts\PackAssetResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * SA pack Null implementation of the typed single-asset resolver.
 *
 * Goal's typed FK arrows in Phase 1 only target GB pack models
 * (gb.savings_account, gb.investment_account). Once SA goals point at
 * SA savings/investment surfaces, swap in concrete resolutions
 * (za.tfsa, za.endowment, etc.) here.
 */
final class ZaPackAssetResolver implements PackAssetResolver
{
    public function resolveAccount(string $assetType, int $id): ?Model
    {
        return null;
    }
}
