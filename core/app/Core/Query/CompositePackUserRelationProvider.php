<?php

declare(strict_types=1);

namespace Fynla\Core\Query;

use Fynla\Core\Contracts\PackUserRelationProvider;
use Fynla\Core\Registry\PackRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Default PackUserRelationProvider implementation. Walks every registered
 * pack and merges results.
 *
 * For Collection-returning `userRelatedModels`, each pack's results
 * concatenate. For the singleton `userRelatedModel`, the first non-null
 * result wins — type tags are pack-scoped (e.g. "gb.protection_profile"),
 * so in practice each tag maps to exactly one pack.
 *
 * Missing / unknown type tags return empty Collection or null per the
 * contract.
 */
final class CompositePackUserRelationProvider implements PackUserRelationProvider
{
    public function __construct(
        private readonly Container $container,
        private readonly PackRegistry $registry,
    ) {
    }

    public function userRelatedModels(int $userId, string $relationType): Collection
    {
        $merged = new Collection();

        foreach ($this->eachPackProvider() as $provider) {
            $merged = $merged->concat($provider->userRelatedModels($userId, $relationType));
        }

        return $merged->values();
    }

    public function userRelatedModel(int $userId, string $relationType): ?Model
    {
        foreach ($this->eachPackProvider() as $provider) {
            $resolved = $provider->userRelatedModel($userId, $relationType);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @return iterable<int, PackUserRelationProvider>
     */
    private function eachPackProvider(): iterable
    {
        foreach ($this->registry->codes() as $code) {
            $bindingKey = 'pack.' . strtolower($code) . '.user_relations';

            if (! $this->container->bound($bindingKey)) {
                continue;
            }

            /** @var PackUserRelationProvider $provider */
            $provider = $this->container->make($bindingKey);
            yield $provider;
        }
    }
}
