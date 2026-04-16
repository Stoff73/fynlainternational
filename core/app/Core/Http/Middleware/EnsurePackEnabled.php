<?php

declare(strict_types=1);

namespace Fynla\Core\Http\Middleware;

use Closure;
use Fynla\Core\Registry\PackRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that ensures a specific country pack is registered and enabled.
 *
 * Used as a parameterised middleware on routes that require a specific pack:
 *   Route::middleware('pack.enabled:gb')->group(function () { ... });
 *
 * Unlike ActiveJurisdictionMiddleware (which reads {cc} from the URL),
 * this middleware takes the required pack code as a parameter, making it
 * suitable for routes that don't have a {cc} segment but still depend
 * on a particular pack being installed.
 */
class EnsurePackEnabled
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
     * @param string $packCode ISO 3166-1 alpha-2 country code (e.g. "gb", "za")
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $packCode): Response
    {
        $packCode = strtoupper($packCode);

        if (! $this->registry->isEnabled($packCode)) {
            return new JsonResponse(
                data: [
                    'error' => 'Pack not available',
                    'code' => 'PACK_NOT_AVAILABLE',
                ],
                status: 404,
            );
        }

        return $next($request);
    }
}
