<?php

declare(strict_types=1);

/**
 * Regression: each unauthenticated auth route must have its OWN rate-limit
 * bucket.
 *
 * Laravel's inline `throttle:N,1` middleware keys unauthenticated requests by
 * sha1("$domain|$ip") with an EMPTY prefix — the route path and the limit `N`
 * are not part of the key. That means every inline-throttled `/auth/*` route on
 * the same IP shared ONE counter, so a multi-step flow throttled itself and a
 * prior burst on one endpoint poisoned another. The fix replaces the inline
 * throttles with per-endpoint named limiters (auth-N keyed by "path|ip").
 */
it('isolates login from register so exhausting one does not throttle the other', function () {
    // Burn the login bucket past its limit (auth-5 = 5/min).
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);
    }

    // Register lives in its own path bucket and must still be reachable.
    $response = $this->postJson('/api/auth/register', []);

    expect($response->status())->not->toBe(429);
});

it('does not let earlier reset steps exhaust the final /reset throttle', function () {
    // The steps an MFA user hits before /reset each consume a throttle slot
    // (the middleware runs before validation, so invalid bodies still count).
    $this->postJson('/api/auth/password-reset/request', ['email' => 'nobody@example.com']);
    $this->postJson('/api/auth/password-reset/verify-email', ['token' => str_repeat('a', 64), 'code' => '000000']);
    $this->postJson('/api/auth/password-reset/verify-mfa', ['token' => str_repeat('a', 64), 'code' => '000000']);

    // /reset has its own bucket, so a genuine first attempt must reach the
    // controller (invalid token -> non-429), NOT be throttled.
    $response = $this->postJson('/api/auth/password-reset/reset', [
        'token' => str_repeat('a', 64),
        'password' => 'NewPassw0rd!',
        'password_confirmation' => 'NewPassw0rd!',
    ]);

    expect($response->status())->not->toBe(429);
});
