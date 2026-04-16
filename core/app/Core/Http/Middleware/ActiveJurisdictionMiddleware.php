<?php

declare(strict_types=1);

namespace Fynla\Core\Http\Middleware;

use Closure;
use Fynla\Core\Registry\PackRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that validates the {cc} route parameter against registered packs
 * and checks the authenticated user's jurisdiction entitlement.
 *
 * Applied to routes with /api/{cc}/* patterns. Core routes without
 * a {cc} parameter pass through unaffected.
 *
 * Phase 0: User entitlement is checked against the FYNLA_ACTIVE_PACKS
 * env var rather than a user_jurisdictions database table.
 *
 * TODO: Workstream D — replace env-based check with user_jurisdictions table query.
 */
class ActiveJurisdictionMiddleware
{
    public function __construct(
        private readonly PackRegistry $registry,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $countryCode = $request->route('cc');

        // Core routes without {cc} parameter pass through
        if ($countryCode === null) {
            return $next($request);
        }

        $countryCode = strtoupper((string) $countryCode);

        // Check if the pack is registered at the installation level
        if (! $this->registry->isEnabled($countryCode)) {
            return new JsonResponse(
                data: [
                    'error' => 'Pack not found',
                    'code' => 'PACK_NOT_FOUND',
                ],
                status: 404,
            );
        }

        // Check user jurisdiction entitlement (if authenticated)
        if ($request->user()) {
            if (! $this->userHasJurisdiction($countryCode)) {
                return new JsonResponse(
                    data: [
                        'error' => 'Jurisdiction not authorised',
                        'code' => 'JURISDICTION_NOT_AUTHORISED',
                    ],
                    status: 403,
                );
            }
        }

        return $next($request);
    }

    /**
     * Check whether the current user has entitlement to the given jurisdiction.
     *
     * Phase 0: Checks the FYNLA_ACTIVE_PACKS env var (comma-separated codes).
     * All authenticated users have access to all packs listed in the env var.
     *
     * TODO: Workstream D — replace with user_jurisdictions table check:
     *   return UserJurisdiction::where('user_id', auth()->id())
     *       ->where('jurisdiction_code', $code)
     *       ->where('active', true)
     *       ->exists();
     *
     * @param string $code ISO 3166-1 alpha-2 country code (uppercase)
     *
     * @return bool True if user has entitlement
     */
    private function userHasJurisdiction(string $code): bool
    {
        // Use getenv() directly rather than Laravel's env() helper,
        // because env() reads from the cached config repository once
        // the app is booted and won't see putenv() changes at runtime.
        $activePacks = getenv('FYNLA_ACTIVE_PACKS');

        if ($activePacks === false || $activePacks === '') {
            // Default: only GB is active
            return $code === 'GB';
        }

        $codes = array_map(
            fn (string $c): string => strtoupper(trim($c)),
            explode(',', $activePacks)
        );

        return in_array($code, $codes, true);
    }
}
