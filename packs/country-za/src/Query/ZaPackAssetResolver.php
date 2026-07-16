<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Query;

use Fynla\Core\Contracts\PackAssetResolver;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Illuminate\Database\Eloquent\Model;

/**
 * SA typed single-asset resolver (WS2).
 *
 * Resolves SA-owned asset FK tags to their models. GB-backed ZA account tags
 * (savings/investment, which are SavingsAccount/InvestmentAccount rows tagged
 * country_code='ZA') are resolved by the GB resolver via the core composite,
 * so only the SA-owned retirement bucket needs resolving here.
 */
final class ZaPackAssetResolver implements PackAssetResolver
{
    public function resolveAccount(string $assetType, int $id): ?Model
    {
        return match ($assetType) {
            'za.retirement_fund' => ZaRetirementFundBucket::query()->find($id),
            default => null,
        };
    }
}
