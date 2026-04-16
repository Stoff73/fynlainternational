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
     * API path prefixes that are UK-specific and should be rewritten.
     *
     * These are the module route prefixes that will move under /api/gb/
     * in Workstream E. Auth, health, and other core routes are NOT rewritten.
     */
    private const REWRITABLE_PREFIXES = [
        'api/protection',
        'api/savings',
        'api/investment',
        'api/retirement',
        'api/estate',
        'api/goals',
        'api/property',
        'api/properties',
        'api/mortgages',
        'api/dashboard',
        'api/plans',
        'api/net-worth',
        'api/profile-completeness',
        'api/recommendations',
        'api/family-members',
        'api/household',
        'api/onboarding',
        'api/journey',
        'api/life-stage',
        'api/life-events',
        'api/business-interests',
        'api/chattels',
        'api/cash-accounts',
        'api/personal-accounts',
        'api/tax',
        'api/holistic-planning',
        'api/income-definitions',
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
