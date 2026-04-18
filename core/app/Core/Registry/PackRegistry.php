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
        // Case-insensitive duplicate check so 'GB' and 'gb' don't both register.
        foreach (array_keys($this->packs) as $existingCode) {
            if (strcasecmp($existingCode, $manifest->code) === 0) {
                throw new RuntimeException(
                    "Country pack '{$manifest->code}' ({$manifest->name}) is already registered."
                );
            }
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
     * Retrieve a pack manifest by its country code (case-insensitive).
     *
     * @param string $code ISO 3166-1 alpha-2 country code
     *
     * @return PackManifest|null The manifest, or null if not registered
     */
    public function byCountryCode(string $code): ?PackManifest
    {
        foreach ($this->packs as $registeredCode => $manifest) {
            if (strcasecmp($registeredCode, $code) === 0) {
                return $manifest;
            }
        }
        return null;
    }

    /**
     * Check whether a pack is registered for a given country code.
     *
     * Case-insensitive: callers passing 'GB', 'gb', or 'Gb' all resolve.
     * Packs register with their own case (typically lowercase); lookups
     * may come from URL params (varied case) or middleware (uppercased).
     *
     * @param string $code ISO 3166-1 alpha-2 country code
     *
     * @return bool True if the pack is registered
     */
    public function isEnabled(string $code): bool
    {
        foreach (array_keys($this->packs) as $registeredCode) {
            if (strcasecmp($registeredCode, $code) === 0) {
                return true;
            }
        }
        return false;
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
