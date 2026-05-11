<?php

declare(strict_types=1);

namespace Fynla\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * URL compatibility layer for the Phase 0 API route transition.
 *
 * Transparently rewrites legacy /api/{module} requests to /api/gb/{module}
 * so that existing clients (mobile app, frontend) continue to work during
 * the migration period. Each rewritten request is logged to help detect
 * clients still using the old URL shape.
 *
 * This middleware should be removed at the end of Phase 1 (60 days after
 * Phase 0 cutover), once all clients have been updated.
 *
 * Per ADR-004 and Phase 0 §8.3: "A compatibility layer transparently
 * rewrites old URLs to the new shape for 60 days, logging each hit."
 */
class LegacyApiRewrite
{
    /**
     * Legacy /api/{module} prefixes that the GB pack now serves under /api/gb/.
     *
     * Only includes prefixes whose routes have actually relocated into
     * packs/country-gb/routes/api.php — rewriting a prefix that has no
     * /api/gb/* counterpart would 404 otherwise-working core routes.
     * Add to this list as further controllers relocate (e.g. dashboard,
     * goals, property, household, life-events gate on R-14b).
     */
    private const REWRITABLE_PREFIXES = [
        'api/protection',
        'api/savings',
        'api/investment',
        'api/retirement',
        'api/estate',
        'api/plans',
        'api/holistic',
        'api/recommendations',
        'api/what-if-scenarios',
        'api/letter-to-spouse',
        'api/tax-info',
        'api/tax-settings',
        'api/tax-year',
        'api/tax',
        'api/admin/protection-actions',
        'api/admin/investment-actions',
        'api/admin/retirement-actions',
        // R-9-final-i: Goals controller relocated to pack.
        'api/goals',
        // R-9-final-ii: LifeEvent controller relocated to pack.
        'api/life-events',
        // R-9-final-iv: Household controller relocated to pack.
        'api/household',
        // R-9-final-v: Property controller relocated to pack.
        'api/properties',
        // R-9-final-vi: Mortgage controller relocated to pack.
        'api/mortgages',
    ];

    /**
     * Handle an incoming request.
     *
     * If the request path matches a rewritable prefix and does NOT already
     * include a jurisdiction code segment (e.g. /api/gb/), rewrite to
     * /api/gb/{rest} and log the hit.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        // Skip if already jurisdictionally scoped (e.g. /api/gb/protection)
        if (preg_match('#^api/[a-z]{2}/#', $path)) {
            return $next($request);
        }

        foreach (self::REWRITABLE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $newPath = 'api/gb/' . substr($path, 4); // "api/" is 4 chars

                try {
                    $logger = app('log');
                    $logger->channel('single')->info('LegacyApiRewrite: rewriting URL', [
                        'from' => '/' . $path,
                        'to' => '/' . $newPath,
                        'method' => $request->method(),
                        'user_agent' => $request->userAgent(),
                        'user_id' => $request->user()?->id,
                    ]);
                } catch (\Throwable) {
                    // Logging is best-effort — don't break the request if the logger isn't available
                }

                // Duplicate the request with the new path
                $request->server->set('REQUEST_URI', '/' . $newPath);
                $request->initialize(
                    $request->query->all(),
                    $request->request->all(),
                    $request->attributes->all(),
                    $request->cookies->all(),
                    $request->files->all(),
                    $request->server->all(),
                    $request->getContent()
                );

                return $next($request);
            }
        }

        return $next($request);
    }
}
