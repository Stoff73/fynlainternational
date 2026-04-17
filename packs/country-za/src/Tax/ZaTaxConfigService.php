<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Tax;

use Illuminate\Support\Facades\DB;

/**
 * Request-scoped read-through cache over za_tax_configurations.
 *
 * Each key returned as a plain minor-unit integer (no float conversion).
 * Nested keys are supported via dot-path resolution: the seeder writes
 * flat rows keyed by dot-path ("income_tax.brackets.3.rate"); this
 * service reassembles them into nested arrays when asked for a prefix.
 *
 * Cache lifetime is the request/test. No DB round-trips after the first
 * access for a given tax year.
 */
class ZaTaxConfigService
{
    /**
     * Cached tables keyed by tax year: ['2026/27' => ['key.path' => int, ...]].
     *
     * @var array<string, array<string, int>>
     */
    private array $rowsByYear = [];

    /**
     * Nested views cached per (tax_year, prefix).
     *
     * @var array<string, mixed>
     */
    private array $nestedCache = [];

    /**
     * Retrieve a single value or a sub-tree.
     *
     * - Exact path hit: returns the stored integer.
     * - Prefix hit: returns a nested array reconstituted from all rows
     *   whose key starts with "<prefix>.".
     * - Miss: returns $default.
     *
     * @param string $taxYear e.g. "2026/27"
     * @param string $keyPath Dot-path key (e.g. "income_tax.brackets")
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get(string $taxYear, string $keyPath, mixed $default = null): mixed
    {
        $rows = $this->loadYear($taxYear);

        if (array_key_exists($keyPath, $rows)) {
            return $rows[$keyPath];
        }

        $cacheKey = $taxYear . '::' . $keyPath;
        if (array_key_exists($cacheKey, $this->nestedCache)) {
            return $this->nestedCache[$cacheKey];
        }

        $prefix = $keyPath . '.';
        $nested = [];
        $found = false;
        foreach ($rows as $path => $value) {
            if (str_starts_with($path, $prefix)) {
                $found = true;
                $this->setByPath($nested, substr($path, strlen($prefix)), $value);
            }
        }

        if (! $found) {
            return $default;
        }

        $this->nestedCache[$cacheKey] = $nested;

        return $nested;
    }

    /**
     * Flush the in-memory cache. Useful for tests that seed new config
     * rows after the service has cached the year.
     */
    public function forget(?string $taxYear = null): void
    {
        if ($taxYear === null) {
            $this->rowsByYear = [];
            $this->nestedCache = [];

            return;
        }

        unset($this->rowsByYear[$taxYear]);

        foreach ($this->nestedCache as $cacheKey => $_) {
            if (str_starts_with($cacheKey, $taxYear . '::')) {
                unset($this->nestedCache[$cacheKey]);
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function loadYear(string $taxYear): array
    {
        if (isset($this->rowsByYear[$taxYear])) {
            return $this->rowsByYear[$taxYear];
        }

        $rows = DB::table('za_tax_configurations')
            ->where('tax_year', $taxYear)
            ->get(['key_path', 'value_cents']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->key_path] = (int) $row->value_cents;
        }

        $this->rowsByYear[$taxYear] = $map;

        return $map;
    }

    /**
     * Insert $value into $nested at the dot-path $path. Numeric segments
     * create list entries; non-numeric create associative keys.
     *
     * @param array<int|string, mixed> $nested
     */
    private function setByPath(array &$nested, string $path, int $value): void
    {
        $segments = explode('.', $path);
        $cursor = &$nested;

        foreach ($segments as $i => $segment) {
            $isLast = ($i === count($segments) - 1);
            $key = ctype_digit($segment) ? (int) $segment : $segment;

            if ($isLast) {
                $cursor[$key] = $value;
                return;
            }

            if (! isset($cursor[$key]) || ! is_array($cursor[$key])) {
                $cursor[$key] = [];
            }
            $cursor = &$cursor[$key];
        }
    }
}
