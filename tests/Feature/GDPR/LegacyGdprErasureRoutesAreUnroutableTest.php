<?php

declare(strict_types=1);

use Fynla\Core\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'gdpr-legacy@example.com',
        'is_preview_user' => false,
    ]);
});

/**
 * Helper — returns true if a route is registered for the given HTTP verb + URI.
 *
 * We assert against the route table (not HTTP status) because routes/web.php
 * has a global SPA catch-all (`Route::get('/{any}', ...)->where('any', '.*')`)
 * that matches every GET, so a missing GET API route returns 200 with HTML
 * and a missing POST returns 405 (Laravel's alternate-verb response). Neither
 * is a clean 404. Checking the route table directly is exact.
 */
function legacyRouteRegistered(string $method, string $uri): bool
{
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! in_array(strtoupper($method), $route->methods(), true)) {
            continue;
        }
        if ($route->uri() === ltrim($uri, '/')) {
            return true;
        }
    }

    return false;
}

describe('G-4-b slice 3 — H-2: legacy GDPR erasure routes are unroutable', function () {
    // The single-step legacy routes bypassed the hardened
    // initiate → verify → execute flow (no MFA, no email code, no
    // confirmation phrase). Frontend never used them — they were a
    // pure attack surface for any stolen session token.

    it('does not register POST api/auth/gdpr/erasure (legacy single-step request)', function () {
        expect(legacyRouteRegistered('POST', 'api/auth/gdpr/erasure'))->toBeFalse();
    });

    it('does not register GET api/auth/gdpr/erasure/status (legacy status check)', function () {
        expect(legacyRouteRegistered('GET', 'api/auth/gdpr/erasure/status'))->toBeFalse();
    });

    it('does not register POST api/auth/gdpr/erasure/{id}/confirm (legacy account-destroying confirm)', function () {
        expect(legacyRouteRegistered('POST', 'api/auth/gdpr/erasure/{id}/confirm'))->toBeFalse();
    });

    it('does not register POST api/auth/gdpr/erasure/{id}/cancel (legacy cancel)', function () {
        expect(legacyRouteRegistered('POST', 'api/auth/gdpr/erasure/{id}/cancel'))->toBeFalse();
    });

    it('returns non-2xx and never invokes confirmErasure when an attacker POSTs to the legacy confirm path with a hijacked session', function () {
        // Practical end-to-end pin: an attacker holding a session token who
        // tries to destroy the account via the old single-call confirm must
        // not get a 2xx success response. Laravel routes this through the
        // SPA catch-all → 405. Either way, it never reaches confirmErasure.
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/9999/confirm');

        expect($response->status())->toBeGreaterThanOrEqual(400);
        expect($response->status())->toBeLessThan(500);
    });
});

describe('G-4-b slice 3 — H-2: hardened 3-step erasure routes still exist', function () {
    // Sanity check — the new flow must still be reachable. We assert via
    // the route table that the new routes are registered.

    it('registers POST api/auth/gdpr/erasure/initiate', function () {
        expect(legacyRouteRegistered('POST', 'api/auth/gdpr/erasure/initiate'))->toBeTrue();
    });

    it('registers POST api/auth/gdpr/erasure/verify', function () {
        expect(legacyRouteRegistered('POST', 'api/auth/gdpr/erasure/verify'))->toBeTrue();
    });

    it('registers POST api/auth/gdpr/erasure/execute', function () {
        expect(legacyRouteRegistered('POST', 'api/auth/gdpr/erasure/execute'))->toBeTrue();
    });
});
