<?php

declare(strict_types=1);

namespace Fynla\Core\Query;

use Fynla\Core\Contracts\PackAssetRepository;
use Fynla\Core\Registry\PackRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

/**
 * Default PackAssetRepository implementation. Iterates every registered
 * pack and merges each pack's per-jurisdiction repository results into
 * a single typed Collection.
 *
 * Bound by CoreServiceProvider as the default resolution of
 * PackAssetRepository. Each pack registers its own repository under the
 * convention `pack.{code}.asset_repo` (e.g. `pack.gb.asset_repo`); the
 * composite resolves those keys against the container at read time.
 *
 * Packs that haven't registered an asset repository are silently skipped
 * — a Phase 1 pack still building out its asset surface won't break the
 * composite for users whose primary jurisdiction is fully implemented.
 */
final class CompositePackAssetRepository implements PackAssetRepository
{
    public function __construct(
        private readonly Container $container,
        private readonly PackRegistry $registry,
    ) {
    }

    public function userAccounts(int $userId): Collection
    {
        return $this->mergePerPack(
            fn (PackAssetRepository $repo) => $repo->userAccounts($userId),
        );
    }

    public function householdAssets(int $householdId): Collection
    {
        return $this->mergePerPack(
            fn (PackAssetRepository $repo) => $repo->householdAssets($householdId),
        );
    }

    /**
     * @param callable(PackAssetRepository): Collection<int, AssetSummary> $reader
     *
     * @return Collection<int, AssetSummary>
     */
    private function mergePerPack(callable $reader): Collection
    {
        $merged = new Collection();

        foreach ($this->registry->codes() as $code) {
            $bindingKey = 'pack.' . strtolower($code) . '.asset_repo';

            if (! $this->container->bound($bindingKey)) {
                continue;
            }

            /** @var PackAssetRepository $repo */
            $repo = $this->container->make($bindingKey);
            $merged = $merged->concat($reader($repo));
        }

        return $merged->values();
    }
}
