<?php

declare(strict_types=1);

namespace Fynla\Core\Services;

use Illuminate\Http\Request;

/**
 * Resolve a visitor's country from an inbound HTTP request.
 *
 * Resolution strategy (first match wins):
 *
 * 1. CF-IPCountry header — set by Cloudflare in production. Authoritative
 *    when present and not 'XX' / 'T1'.
 * 2. MaxMind GeoLite2 reader — optional. Pass a callable reader at
 *    construction time that maps an IP to a country code, or null to skip.
 *    The reader's exceptions are swallowed so a DB outage cannot brick
 *    registration; unknown IPs fall through to step 3.
 * 3. GEO_OVERRIDE env — for local dev / tests. If set and non-empty,
 *    returns its value verbatim (upper-cased).
 *
 * Return value: uppercase ISO 3166-1 alpha-2 code ("GB", "ZA") or null
 * when no resolver answered.
 *
 * Intentional omissions for this workstream (WS 0.4):
 * - No caching. Per-request cost is negligible (a header read or single
 *   MaxMind lookup).
 * - No IP-range allowlists. Not relevant for country-level resolution.
 * - The actual MaxMind DB is not bundled here — the reader is injected.
 *   Operational responsibility to keep the DB current lives with deploy
 *   tooling, not this class.
 */
final class GeoLocationService
{
    /**
     * Cloudflare's "unknown country" sentinel values that should be treated
     * as absent rather than authoritative.
     */
    private const CLOUDFLARE_UNKNOWN = ['', 'XX', 'T1'];

    /**
     * Callable that maps an IP string to an ISO country code, or null to
     * indicate "no MaxMind reader configured for this environment".
     *
     * @var (callable(string): ?string)|null
     */
    private $maxmindReader;

    /**
     * @param (callable(string): ?string)|null $maxmindReader
     *   A callable accepting an IP string and returning an ISO alpha-2
     *   country code or null. Kept as a callable (not a class dependency)
     *   so operational choice of reader library is left to the service
     *   provider that constructs this service.
     */
    public function __construct(?callable $maxmindReader = null)
    {
        $this->maxmindReader = $maxmindReader;
    }

    /**
     * Resolve the visitor's country code from the request.
     *
     * @return string|null Uppercase ISO 3166-1 alpha-2 code or null if unresolved
     */
    public function countryFromRequest(Request $request): ?string
    {
        $fromHeader = $this->fromCloudflareHeader($request);
        if ($fromHeader !== null) {
            return $fromHeader;
        }

        $fromMaxmind = $this->fromMaxmind($request->ip());
        if ($fromMaxmind !== null) {
            return $fromMaxmind;
        }

        return $this->fromEnvOverride();
    }

    private function fromCloudflareHeader(Request $request): ?string
    {
        $header = $request->headers->get('CF-IPCountry');
        if ($header === null) {
            return null;
        }

        $normalised = strtoupper(trim($header));
        if (in_array($normalised, self::CLOUDFLARE_UNKNOWN, true)) {
            return null;
        }

        return $normalised;
    }

    private function fromMaxmind(?string $ip): ?string
    {
        if ($this->maxmindReader === null || $ip === null || $ip === '') {
            return null;
        }

        try {
            $code = ($this->maxmindReader)($ip);
        } catch (\Throwable) {
            // Reader failures must never crash the request — unknown IP is
            // an acceptable outcome. Registration's country-selector fallback
            // handles users we can't geolocate.
            return null;
        }

        if (! is_string($code) || $code === '') {
            return null;
        }

        return strtoupper(trim($code));
    }

    private function fromEnvOverride(): ?string
    {
        $override = getenv('GEO_OVERRIDE');
        if ($override === false || $override === '') {
            return null;
        }

        return strtoupper(trim($override));
    }
}
