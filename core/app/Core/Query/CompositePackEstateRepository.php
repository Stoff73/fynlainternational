<?php

declare(strict_types=1);

namespace Fynla\Core\Query;

use Fynla\Core\Contracts\PackEstateRepository;
use Fynla\Core\Registry\PackRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Default PackEstateRepository implementation. Iterates every registered
 * pack and merges each pack's per-jurisdiction estate repository
 * results.
 *
 * For Collection-returning methods, results from every pack concatenate.
 * For the singleton `ihtProfileForUser` method, the first non-null result
 * wins — a user belongs to one IHT regime at a time, so multiple packs
 * are not expected to return profiles for the same user. (If a future
 * dual-jurisdiction Phase 2 user lands in this state, the composite can
 * be tightened to fail-fast or pick by active-jurisdiction context.)
 */
final class CompositePackEstateRepository implements PackEstateRepository
{
    public function __construct(
        private readonly Container $container,
        private readonly PackRegistry $registry,
    ) {
    }

    public function liabilitiesForUser(int $userId): Collection
    {
        return $this->mergeCollections(
            fn (PackEstateRepository $repo) => $repo->liabilitiesForUser($userId),
        );
    }

    public function trustsForUser(int $userId): Collection
    {
        return $this->mergeCollections(
            fn (PackEstateRepository $repo) => $repo->trustsForUser($userId),
        );
    }

    public function ihtProfileForUser(int $userId): ?Model
    {
        foreach ($this->eachPackRepo() as $repo) {
            $profile = $repo->ihtProfileForUser($userId);
            if ($profile !== null) {
                return $profile;
            }
        }

        return null;
    }

    public function estateAssetsForUser(int $userId): Collection
    {
        return $this->mergeCollections(
            fn (PackEstateRepository $repo) => $repo->estateAssetsForUser($userId),
        );
    }

    public function giftsForUser(int $userId): Collection
    {
        return $this->mergeCollections(
            fn (PackEstateRepository $repo) => $repo->giftsForUser($userId),
        );
    }

    public function lpasForUser(int $userId): Collection
    {
        return $this->mergeCollections(
            fn (PackEstateRepository $repo) => $repo->lpasForUser($userId),
        );
    }

    /**
     * @param callable(PackEstateRepository): Collection<int, Model> $reader
     *
     * @return Collection<int, Model>
     */
    private function mergeCollections(callable $reader): Collection
    {
        $merged = new Collection();

        foreach ($this->eachPackRepo() as $repo) {
            $merged = $merged->concat($reader($repo));
        }

        return $merged->values();
    }

    /**
     * @return iterable<int, PackEstateRepository>
     */
    private function eachPackRepo(): iterable
    {
        foreach ($this->registry->codes() as $code) {
            $bindingKey = 'pack.' . strtolower($code) . '.estate_repo';

            if (! $this->container->bound($bindingKey)) {
                continue;
            }

            /** @var PackEstateRepository $repo */
            $repo = $this->container->make($bindingKey);
            yield $repo;
        }
    }
}
