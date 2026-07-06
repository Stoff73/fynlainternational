<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Test Gauntlet G-2-a — the unauthenticated-rejection column, enforced across
 * EVERY api/* route at once.
 *
 * Instead of one auth test per controller, this walks the whole route table
 * and asserts every route is EITHER sanctum-guarded, token-gated
 * (AgentTokenAuth), OR on an explicit allow-list of intentionally public
 * endpoints. A new route with no guard fails the build — the cheapest possible
 * backstop against the "forgot auth:sanctum" regression (which is exactly how
 * the SPA-catch-all 200s were masking auth gaps before 2026-07-06).
 */

/**
 * Intentionally public api/* routes (auth entry points, health, webhooks,
 * preview, public marketing). Adding a route here is a deliberate decision —
 * the reviewer must confirm it carries no user data or is otherwise gated.
 */
const INTENTIONALLY_PUBLIC = [
    'api/contact',
    'api/bug-report',
    'api/auth/login',
    'api/auth/register',
    'api/auth/verify-code',
    'api/auth/resend-code',
    'api/auth/logout-beacon',
    'api/auth/mfa/verify',
    'api/auth/mfa/recovery',
    'api/auth/password-reset/request',
    'api/auth/password-reset/verify-email',
    'api/auth/password-reset/resend-code',
    'api/auth/password-reset/verify-mfa',
    'api/auth/password-reset/mfa-recovery',
    'api/auth/password-reset/reset',
    'api/preview/personas',
    'api/preview/login/{personaId}',
    'api/payment/plans',
    'api/webhooks/revolut',
    'api/v1/health',
    'api/xx/health',
];

it('every api route is guarded, token-gated, or explicitly public', function () {
    $unguarded = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();
        if (! str_starts_with($uri, 'api/')) {
            continue;
        }

        // gatherMiddleware() may return either the resolved class
        // (App\Http\Middleware\Authenticate:sanctum) or the unresolved alias
        // (auth:sanctum) depending on how the route group was registered —
        // match both by the 'sanctum' substring.
        $middleware = collect($route->gatherMiddleware())->filter(fn ($m) => is_string($m));
        $sanctum = $middleware->contains(fn (string $m) => str_contains($m, 'sanctum'));
        $tokenGated = $middleware->contains(fn (string $m) => str_contains($m, 'AgentTokenAuth') || str_contains($m, 'agent.token'));

        if ($sanctum || $tokenGated || in_array($uri, INTENTIONALLY_PUBLIC, true)) {
            continue;
        }

        $unguarded[] = $route->methods()[0].' '.$uri;
    }

    expect($unguarded)->toBeEmpty(
        "Unguarded api/* routes found (add auth:sanctum, or to INTENTIONALLY_PUBLIC if truly public):\n".
        implode("\n", array_unique($unguarded))
    );
});

it('a sanctum-guarded endpoint rejects an unauthenticated request with 401', function () {
    // Spot-check the actual runtime behaviour behind the static guard check.
    $this->getJson('/api/dashboard')->assertUnauthorized();
    $this->getJson('/api/gb/net-worth/overview')->assertUnauthorized();
    $this->getJson('/api/gb/protection')->assertUnauthorized();
});

it('the intentionally-public allow-list only names routes that exist', function () {
    $allUris = collect(Route::getRoutes())->map->uri()->all();

    foreach (INTENTIONALLY_PUBLIC as $uri) {
        expect(in_array($uri, $allUris, true))->toBeTrue("Public allow-list names a nonexistent route: {$uri}");
    }
});
