<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Query;

use Fynla\Core\Contracts\PackUserRelationProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * SA pack Null implementation of the user-scoped relation provider.
 *
 * Phase 1 SA has no equivalent of User's GB-specific per-module
 * relations (UK Protection profile/policies, UK Property + Mortgage,
 * UK pensions, UK Letter to Spouse). When SA models for similar
 * surfaces land in later workstreams, swap in concrete resolutions
 * (za.tfsa, za.ra, etc.) here.
 *
 * Bound for structural symmetry so the composite finds a real
 * implementation under `pack.za.user_relations` rather than silently
 * skipping.
 */
final class ZaPackUserRelationProvider implements PackUserRelationProvider
{
    public function modelClassFor(string $relationType): ?string
    {
        return null;
    }

    public function userRelatedModels(int $userId, string $relationType): Collection
    {
        return new Collection();
    }

    public function userRelatedModel(int $userId, string $relationType): ?Model
    {
        return null;
    }
}
