<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Pack-mediated read interface for user-scoped relations that need full
 * Eloquent Models (not AssetSummary VOs).
 *
 * Replaces the pack-namespaced `hasMany` / `hasOne` literals that core
 * User holds for per-module relations (properties, mortgages, DC/DB
 * pensions, protection policies, investment accounts, etc.). Callers
 * expect concrete Model instances they can iterate, sum on columns,
 * and pass to per-pack Resources — AssetSummary VOs lose that shape.
 *
 * Type tags follow the AssetSummary convention ("gb.dc_pension",
 * "gb.protection_profile", "gb.property", …). Unknown / unregistered
 * type tags resolve to empty Collection or null per the method
 * contract rather than throwing — consumers handle missing surfaces
 * gracefully (e.g. when a pack hasn't shipped a given relation yet).
 *
 * Mirrors the personal-ownership semantics of the legacy User
 * `hasMany`: queries `WHERE user_id = ?` only, NOT joint-owner aware.
 * Joint-aware aggregations remain on `PackAssetRepository::userAccounts`.
 */
interface PackUserRelationProvider
{
    /**
     * Resolve full Eloquent Models for a user-scoped hasMany pack relation.
     *
     * @param string $relationType Pack-scoped type tag (e.g. "gb.dc_pension")
     *
     * @return Collection<int, Model>
     */
    public function userRelatedModels(int $userId, string $relationType): Collection;

    /**
     * Resolve the single Eloquent Model for a user-scoped hasOne pack relation.
     *
     * @param string $relationType Pack-scoped type tag (e.g. "gb.protection_profile")
     */
    public function userRelatedModel(int $userId, string $relationType): ?Model;
}
