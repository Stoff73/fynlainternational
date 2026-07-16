<?php

declare(strict_types=1);

namespace Fynla\Core\Http\Middleware;

use Closure;
use Fynla\Core\Registry\PackRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces per-user pack access on jurisdictional API routes.
 *
 * The pack code is derived from the request path (api/gb/* -> GB, api/za/* -> ZA)
 * since routes carry the prefix rather than a {cc} parameter. Core routes
 * (api/auth, api/user, …) match no pack prefix and pass through. An
 * authenticated user who holds any jurisdictions must hold a (non-deactivated)
 * one for the derived pack, else 403. A row-less user is not blocked
 * (fail-open) — every real user gets a jurisdiction at signup and via
 * jurisdictions:backfill, so row-less is only a transient/legacy state.
 * Packs not installed at all give 404.
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
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $countryCode = $this->packCodeFromPath($request);

        // Core routes (no pack prefix) pass through.
        if ($countryCode === null) {
            return $next($request);
        }

        if (! $this->registry->isEnabled($countryCode)) {
            return new JsonResponse(
                data: ['error' => 'Pack not found', 'code' => 'PACK_NOT_FOUND'],
                status: 404,
            );
        }

        $user = $request->user();

        // $user->jurisdictions already excludes soft-deactivated rows. Fail-open
        // for row-less users (they hold none yet); enforce for scoped users.
        if ($user !== null
            && $user->jurisdictions->isNotEmpty()
            && ! $user->jurisdictions->contains('code', $countryCode)) {
            return new JsonResponse(
                data: ['error' => 'Jurisdiction not authorised', 'code' => 'JURISDICTION_NOT_AUTHORISED'],
                status: 403,
            );
        }

        return $next($request);
    }

    /**
     * Derive the pack code from the request path: api/gb/* -> GB, api/za/* -> ZA.
     * Core routes match neither and return null.
     */
    private function packCodeFromPath(Request $request): ?string
    {
        foreach ($this->registry->codes() as $code) {
            if ($request->is('api/'.strtolower($code).'/*')) {
                return strtoupper($code);
            }
        }

        return null;
    }
}
