<?php

declare(strict_types=1);

namespace Fynla\Core\Registry;

/**
 * Value object describing a country pack's metadata.
 *
 * Immutable once constructed. Carries the information the registry
 * and framework need to wire up a pack's routes, navigation, and
 * database table prefix.
 */
final class PackManifest
{
    /**
     * @param string $code        ISO 3166-1 alpha-2 country code (e.g. "GB", "ZA")
     * @param string $name        Human-readable country name (e.g. "United Kingdom")
     * @param string $currency    ISO 4217 currency code (e.g. "GBP", "ZAR")
     * @param string $locale      POSIX locale identifier (e.g. "en_GB", "en_ZA")
     * @param string $tablePrefix Database table prefix for this pack (e.g. "gb_", "za_")
     * @param array  $navigation  Navigation items to merge into the sidebar
     * @param array  $routes      Route file paths or route group definitions
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $currency,
        public readonly string $locale,
        public readonly string $tablePrefix,
        public readonly array $navigation = [],
        public readonly array $routes = [],
    ) {
    }

    /**
     * Create a PackManifest from an associative array.
     *
     * Useful for hydrating from config files or database records.
     *
     * @param array{
     *     code: string,
     *     name: string,
     *     currency: string,
     *     locale: string,
     *     table_prefix: string,
     *     navigation?: array,
     *     routes?: array
     * } $data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            name: $data['name'],
            currency: $data['currency'],
            locale: $data['locale'],
            tablePrefix: $data['table_prefix'],
            navigation: $data['navigation'] ?? [],
            routes: $data['routes'] ?? [],
        );
    }
}
