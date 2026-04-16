<?php

declare(strict_types=1);

namespace Fynla\Core\Registry;

use RuntimeException;

/**
 * Singleton registry holding all registered country packs.
 *
 * Country packs register their manifests here during application boot.
 * The registry prevents duplicate registrations and provides lookup
 * methods for the framework and middleware to resolve the active pack.
 */
final class PackRegistry
{
    /**
     * Registered pack manifests keyed by country code.
     *
     * @var array<string, PackManifest>
     */
    private array $packs = [];

    /**
     * Register a country pack manifest.
     *
     * @param PackManifest $manifest The pack manifest to register
     *
     * @throws RuntimeException If a pack with the same country code is already registered
     */
    public function register(PackManifest $manifest): void
    {
        if (isset($this->packs[$manifest->code])) {
            throw new RuntimeException(
                "Country pack '{$manifest->code}' ({$manifest->name}) is already registered."
            );
        }

        $this->packs[$manifest->code] = $manifest;
    }

    /**
     * List all enabled (registered) pack manifests.
     *
     * @return array<string, PackManifest> Manifests keyed by country code
     */
    public function listEnabled(): array
    {
        return $this->packs;
    }

    /**
     * Retrieve a pack manifest by its country code.
     *
     * @param string $code ISO 3166-1 alpha-2 country code
     *
     * @return PackManifest|null The manifest, or null if not registered
     */
    public function byCountryCode(string $code): ?PackManifest
    {
        return $this->packs[$code] ?? null;
    }

    /**
     * Check whether a pack is registered for a given country code.
     *
     * @param string $code ISO 3166-1 alpha-2 country code
     *
     * @return bool True if the pack is registered
     */
    public function isEnabled(string $code): bool
    {
        return isset($this->packs[$code]);
    }

    /**
     * Get the number of registered packs.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->packs);
    }

    /**
     * Get all registered country codes.
     *
     * @return array<int, string> List of ISO 3166-1 alpha-2 country codes
     */
    public function codes(): array
    {
        return array_keys($this->packs);
    }
}
