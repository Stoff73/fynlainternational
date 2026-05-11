<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

use Fynla\Core\Query\AssetSummary;
use Illuminate\Support\Collection;

/**
 * Pack-mediated read interface for cross-module asset aggregations.
 *
 * Replaces the pack-namespaced `hasMany` literals that core models
 * (User, Household) currently hold when they need to surface
 * portfolio-shaped data (investments, savings, retirement, protection,
 * properties, business interests, chattels) for a user or household.
 *
 * The composite default implementation iterates every registered pack
 * and merges results, so consumers get a single typed Collection without
 * knowing which pack each AssetSummary originated in.
 */
interface PackAssetRepository
{
    /**
     * Summaries of every pack-owned asset where the user is the primary
     * or joint owner.
     *
     * @return Collection<int, AssetSummary>
     */
    public function userAccounts(int $userId): Collection;

    /**
     * Summaries of every pack-owned asset attached to a household
     * (combining all members' personally-owned and jointly-owned assets).
     *
     * @return Collection<int, AssetSummary>
     */
    public function householdAssets(int $householdId): Collection;
}
