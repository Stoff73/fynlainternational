<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Pack-mediated read interface for the 6 Estate-shaped relationships
 * the core User model currently holds via pack-namespaced `hasMany`
 * literals (liabilities, trusts, IHT profile, estate assets, gifts, LPAs).
 *
 * Each method returns a typed Eloquent Collection — the composite default
 * iterates every registered pack and merges results. Concrete pack models
 * stay encapsulated inside their packs; cross-module callers depend only
 * on the abstract Model return type and feature-test the shape they need.
 *
 * Estate is split into its own contract (rather than folding into
 * PackAssetRepository) because the 6 methods are typed differently per
 * concept (some Collections, one ?Model for the singleton ihtProfile)
 * and callers reach for them by name, not by polymorphic asset shape.
 */
interface PackEstateRepository
{
    /**
     * Liabilities (loans, credit cards, unsecured debt) recorded under
     * a user's estate profile.
     *
     * @return Collection<int, Model>
     */
    public function liabilitiesForUser(int $userId): Collection;

    /**
     * Trusts the user is settlor or trustee of.
     *
     * @return Collection<int, Model>
     */
    public function trustsForUser(int $userId): Collection;

    /**
     * The user's IHT profile singleton (one per user), if any.
     */
    public function ihtProfileForUser(int $userId): ?Model;

    /**
     * Estate assets (inheritance-tax-scoped assets distinct from the
     * cross-module portfolio assets exposed via PackAssetRepository).
     *
     * @return Collection<int, Model>
     */
    public function estateAssetsForUser(int $userId): Collection;

    /**
     * Lifetime gifts recorded against the user.
     *
     * @return Collection<int, Model>
     */
    public function giftsForUser(int $userId): Collection;

    /**
     * Lasting Powers of Attorney the user has lodged.
     *
     * @return Collection<int, Model>
     */
    public function lpasForUser(int $userId): Collection;
}
