<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Query;

use Fynla\Core\Contracts\PackAssetResolver;
use Fynla\Packs\Gb\Models\Investment\InvestmentAccount;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Illuminate\Database\Eloquent\Model;

/**
 * GB pack implementation of the typed single-asset resolver.
 *
 * Goal currently holds two pack-namespaced `belongsTo` literals
 * (`linkedSavingsAccount`, `linkedInvestmentAccount`). After R-14b-v
 * Goal lives in core and surfaces those relationships through this
 * resolver — core stores the FK id + type tag and delegates concrete
 * model resolution to the pack at read time.
 *
 * Unknown / unregistered type tags return null rather than throwing,
 * so consumers handle missing assets gracefully (e.g. after a model
 * has been hard-deleted).
 */
final class GbPackAssetResolver implements PackAssetResolver
{
    public function resolveAccount(string $assetType, int $id): ?Model
    {
        return match ($assetType) {
            'gb.savings_account' => SavingsAccount::query()->find($id),
            'gb.investment_account' => InvestmentAccount::query()->find($id),
            default => null,
        };
    }
}
