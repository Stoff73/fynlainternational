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
     * Resolve the concrete Eloquent Model class name for a user-scoped
     * pack relation type tag. Core models call this from inside Eloquent
     * `hasMany(...)` / `hasOne(...)` declarations so the relationship
     * arrow itself doesn't hold a static pack-namespaced literal.
     *
     * Returning a class string (not a Model instance) keeps the Eloquent
     * relation API intact: callers retain `()->where()`, `()->exists()`,
     * eager-loading via `User::with([...])`, and lazy-loaded property
     * access.
     *
     * Unknown / unregistered type tags return null.
     *
     * @param string $relationType Pack-scoped type tag (e.g. "gb.dc_pension")
     *
     * @return class-string<Model>|null
     */
    public function modelClassFor(string $relationType): ?string;

    /**
     * Resolve full Eloquent Models for a user-scoped hasMany pack relation.
     *
     * Provided as an alternative to the class-string driven hasMany for
     * cases where the caller wants an immediate Collection without going
     * through Eloquent's relation builder.
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
