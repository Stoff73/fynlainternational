<?php

declare(strict_types=1);

namespace Fynla\Core\Jurisdiction;

/**
 * Resolve a free-text location to an ISO 3166-1 alpha-2 country code.
 *
 * Accepts city names ("Cape Town"), country names ("United Kingdom"),
 * country codes ("GB", "gb", "ZA"), and common aliases ("UK", "SA").
 * Returns null for anything the curated list cannot match.
 *
 * The curated list covers the two supported countries in Phase 0 (UK and
 * SA) plus their major financial centres. It intentionally does NOT try to
 * be a full gazetteer — that scales badly and misleads users. If a user
 * types a city we don't recognise, the form falls back to an explicit
 * country-picker dropdown (Workstream 0.6 UI — not in this backend piece).
 *
 * Resolution order:
 *   1. Exact ISO-2 code match (case-insensitive) — "GB", "gb", "za"
 *   2. Aliases map — "UK", "Britain", "SA"
 *   3. Countries map — "United Kingdom", "South Africa"
 *   4. Cities map — "London", "Cape Town", "Johannesburg"
 *
 * Match is case-insensitive; whitespace is trimmed. Punctuation beyond
 * spaces and hyphens is not normalised — that's a potential false-negative
 * class that the UI-picker fallback handles.
 */
final class CountryResolver
{
    /**
     * Supported ISO 3166-1 alpha-2 codes.
     */
    private const SUPPORTED_CODES = ['gb', 'za'];

    /**
     * Common short-form names that should route to a canonical code.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'uk' => 'gb',
        'u.k.' => 'gb',
        'britain' => 'gb',
        'great britain' => 'gb',
        'england' => 'gb',
        'scotland' => 'gb',
        'wales' => 'gb',
        'northern ireland' => 'gb',
        'sa' => 'za',
        'r.s.a.' => 'za',
    ];

    /**
     * Official country name → code.
     *
     * @var array<string, string>
     */
    private const COUNTRIES = [
        'united kingdom' => 'gb',
        'united kingdom of great britain and northern ireland' => 'gb',
        'south africa' => 'za',
        'republic of south africa' => 'za',
    ];

    /**
     * Major financial / population centres → code. Keep this short; users
     * who type obscure cities get the picker fallback.
     *
     * @var array<string, string>
     */
    private const CITIES = [
        // United Kingdom
        'london' => 'gb',
        'birmingham' => 'gb',
        'manchester' => 'gb',
        'leeds' => 'gb',
        'glasgow' => 'gb',
        'edinburgh' => 'gb',
        'bristol' => 'gb',
        'cardiff' => 'gb',
        'belfast' => 'gb',
        'liverpool' => 'gb',
        'sheffield' => 'gb',
        'newcastle' => 'gb',
        'nottingham' => 'gb',
        // South Africa
        'cape town' => 'za',
        'johannesburg' => 'za',
        'pretoria' => 'za',
        'durban' => 'za',
        'port elizabeth' => 'za',
        'gqeberha' => 'za',
        'bloemfontein' => 'za',
        'stellenbosch' => 'za',
        'sandton' => 'za',
        'centurion' => 'za',
    ];

    /**
     * Resolve a location string to a lowercase ISO 3166-1 alpha-2 code.
     *
     * @return string|null Lowercase ISO code ('gb', 'za') or null if unresolved
     */
    public function resolve(?string $location): ?string
    {
        if ($location === null) {
            return null;
        }

        $normalised = $this->normalise($location);
        if ($normalised === '') {
            return null;
        }

        // 1. Direct ISO-2 code
        if (strlen($normalised) === 2 && in_array($normalised, self::SUPPORTED_CODES, true)) {
            return $normalised;
        }

        // 2. Aliases
        if (isset(self::ALIASES[$normalised])) {
            return self::ALIASES[$normalised];
        }

        // 3. Official country name
        if (isset(self::COUNTRIES[$normalised])) {
            return self::COUNTRIES[$normalised];
        }

        // 4. City name
        if (isset(self::CITIES[$normalised])) {
            return self::CITIES[$normalised];
        }

        return null;
    }

    /**
     * Check whether a code is in the supported set — useful for callers
     * that want to validate a stored country_code before persisting.
     */
    public function isSupported(string $code): bool
    {
        return in_array($this->normalise($code), self::SUPPORTED_CODES, true);
    }

    /**
     * List of supported codes. Exposed for seeders / fixtures.
     *
     * @return array<int, string>
     */
    public function supportedCodes(): array
    {
        return self::SUPPORTED_CODES;
    }

    private function normalise(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
}
