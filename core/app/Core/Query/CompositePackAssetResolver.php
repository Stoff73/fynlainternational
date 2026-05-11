<?php

declare(strict_types=1);

namespace Fynla\Core\Query;

use Fynla\Core\Contracts\PackAssetResolver;
use Fynla\Core\Registry\PackRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Default PackAssetResolver implementation. Walks every registered pack
 * and returns the first non-null resolution.
 *
 * The type tag convention is pack-scoped (e.g. "gb.investment_account"),
 * so in practice each tag maps to exactly one pack — but the composite
 * doesn't enforce that, leaving room for typed tags that span packs in
 * later phases (e.g. cross-border holdings). Missing / unknown type tags
 * return null rather than throwing, matching the contract.
 */
final class CompositePackAssetResolver implements PackAssetResolver
{
    public function __construct(
        private readonly Container $container,
        private readonly PackRegistry $registry,
    ) {
    }

    public function resolveAccount(string $assetType, int $id): ?Model
    {
        foreach ($this->registry->codes() as $code) {
            $bindingKey = 'pack.' . strtolower($code) . '.asset_resolver';

            if (! $this->container->bound($bindingKey)) {
                continue;
            }

            /** @var PackAssetResolver $resolver */
            $resolver = $this->container->make($bindingKey);
            $resolved = $resolver->resolveAccount($assetType, $id);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }
}
