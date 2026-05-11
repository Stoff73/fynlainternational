<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Pack-mediated single-asset resolver for typed FK arrows in core models.
 *
 * Replaces the pack-namespaced `belongsTo` literals the Goal model
 * currently holds for its FK columns (linkedSavingsAccount,
 * linkedInvestmentAccount), so core can store the FK id + type tag and
 * delegate concrete-model resolution to the pack at read time.
 *
 * Type tags follow the AssetSummary convention ("gb.investment_account",
 * "gb.savings_account", "gb.property", …). Unknown / unregistered type
 * tags return null rather than throwing — consumers handle missing
 * assets gracefully (e.g. after a model is hard-deleted).
 */
interface PackAssetResolver
{
    /**
     * Resolve a pack-owned asset by its type tag and primary key.
     *
     * @param string $assetType Pack-scoped type tag (e.g. "gb.investment_account")
     * @param int    $id        Primary key on the concrete model
     */
    public function resolveAccount(string $assetType, int $id): ?Model;
}
