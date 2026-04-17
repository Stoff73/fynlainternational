<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * These tests cover the happy path for the ActiveJurisdictionMiddleware now
 * that it's wired into the api middleware group (Workstream 0.3b).
 *
 * The middleware's PACK_NOT_FOUND (404) and JURISDICTION_NOT_AUTHORISED (403)
 * branches are covered by the unit tests in
 * tests/Unit/Core/Http/Middleware/ActiveJurisdictionMiddlewareTest.php —
 * those exercise the middleware's handle() method directly with a crafted
 * request. Integration-level 404/403 coverage arrives once real /api/{cc}/*
 * routes land in Workstream 0.6.
 */

it('passes through API routes without a {cc} parameter (no-op for UK-only)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // /api/auth/user has no {cc} parameter, so the middleware short-circuits
    // on the first branch (countryCode === null) and passes the request on.
    // Any 5xx here means the middleware is not a no-op — regression.
    $this->getJson('/api/auth/user')->assertOk();
});
