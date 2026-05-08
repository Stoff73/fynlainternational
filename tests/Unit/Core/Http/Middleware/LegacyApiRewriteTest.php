<?php

declare(strict_types=1);

use Fynla\Core\Http\Middleware\LegacyApiRewrite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('rewrites legacy /api/protection to /api/gb/protection', function () {
    $middleware = new LegacyApiRewrite();

    $request = Request::create('/api/protection/policies', 'GET');

    $response = $middleware->handle($request, function (Request $r) {
        return new Response('ok: ' . $r->path());
    });

    expect($response->getContent())->toContain('api/gb/protection/policies');
});

it('rewrites legacy /api/savings to /api/gb/savings', function () {
    $middleware = new LegacyApiRewrite();

    $request = Request::create('/api/savings/accounts', 'GET');

    $response = $middleware->handle($request, function (Request $r) {
        return new Response('ok: ' . $r->path());
    });

    expect($response->getContent())->toContain('api/gb/savings/accounts');
});

it('does not rewrite already-scoped /api/gb/ requests', function () {
    $middleware = new LegacyApiRewrite();

    $request = Request::create('/api/gb/protection/policies', 'GET');

    $response = $middleware->handle($request, function (Request $r) {
        return new Response('ok: ' . $r->path());
    });

    expect($response->getContent())->toContain('api/gb/protection/policies');
    // Should still work, just not double-rewritten
    expect($response->getContent())->not->toContain('api/gb/gb/');
});

it('does not rewrite non-module API paths', function () {
    $middleware = new LegacyApiRewrite();

    $request = Request::create('/api/auth/login', 'POST');

    $response = $middleware->handle($request, function (Request $r) {
        return new Response('ok: ' . $r->path());
    });

    expect($response->getContent())->toBe('ok: api/auth/login');
});

it('preserves POST data through rewrite', function () {
    $middleware = new LegacyApiRewrite();

    $request = Request::create('/api/protection/policies', 'POST', ['name' => 'test']);

    $response = $middleware->handle($request, function (Request $r) {
        return new Response($r->input('name') . ':' . $r->path());
    });

    expect($response->getContent())->toBe('test:api/gb/protection/policies');
});

it('rewrites all known pack-relocated module prefixes', function () {
    $middleware = new LegacyApiRewrite();

    // Mirrors LegacyApiRewrite::REWRITABLE_PREFIXES — modules whose
    // controllers have relocated into packs/country-gb/routes/api.php.
    $prefixes = [
        'protection', 'savings', 'investment', 'retirement', 'estate',
        'plans', 'holistic', 'recommendations', 'what-if-scenarios',
        'letter-to-spouse', 'tax-info', 'tax-settings', 'tax-year', 'tax',
        'admin/protection-actions', 'admin/investment-actions', 'admin/retirement-actions',
    ];

    foreach ($prefixes as $prefix) {
        $request = Request::create("/api/{$prefix}/test", 'GET');

        $response = $middleware->handle($request, function (Request $r) {
            return new Response($r->path());
        });

        expect($response->getContent())->toBe("api/gb/{$prefix}/test",
            "Failed to rewrite /api/{$prefix}/test");
    }
});

it('does not rewrite UK module prefixes still resident in core routes', function () {
    // These modules' controllers haven't relocated to packs/country-gb yet
    // (gated on R-14b). Their /api/{module}/* paths must stay as-is so
    // core's routes/api.php continues to serve them.
    $middleware = new LegacyApiRewrite();

    $coreResidentPrefixes = [
        'goals', 'property', 'properties', 'mortgages', 'dashboard',
        'net-worth', 'family-members', 'household', 'profile-completeness',
        'onboarding', 'journey', 'life-stage', 'life-events',
        'business-interests', 'chattels', 'cash-accounts', 'personal-accounts',
    ];

    foreach ($coreResidentPrefixes as $prefix) {
        $request = Request::create("/api/{$prefix}/test", 'GET');

        $response = $middleware->handle($request, function (Request $r) {
            return new Response($r->path());
        });

        expect($response->getContent())->toBe("api/{$prefix}/test",
            "Should not have rewritten /api/{$prefix}/test (still resident in core routes)");
    }
});
