<?php

declare(strict_types=1);

use Fynla\Core\Http\Middleware\ActiveJurisdictionMiddleware;
use Fynla\Core\Registry\PackManifest;
use Fynla\Core\Registry\PackRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * WS1: the middleware derives the pack from the request path (api/gb/* -> GB,
 * api/za/* -> ZA) and enforces it against the user's jurisdictions. Row-less
 * users fail open; scoped users are blocked from packs they don't hold.
 * (End-to-end coverage lives in tests/Feature/Security/JurisdictionEnforcementTest.)
 */
beforeEach(function () {
    $registry = new PackRegistry();
    $registry->register(new PackManifest(
        code: 'GB', name: 'United Kingdom', currency: 'GBP', locale: 'en_GB', tablePrefix: 'gb_',
    ));
    $this->middleware = new ActiveJurisdictionMiddleware($registry);
});

function jurisdictionRequest(string $path, ?array $codes = null): Request
{
    $request = Request::create($path, 'GET');

    if ($codes !== null) {
        $user = new stdClass();
        $user->id = 1;
        $user->jurisdictions = collect(array_map(fn (string $c) => (object) ['code' => $c], $codes));
        $request->setUserResolver(fn () => $user);
    }

    return $request;
}

function runJurisdiction(object $middleware, Request $request)
{
    return $middleware->handle($request, fn () => new Response('OK', 200));
}

it('passes through a core route with no pack prefix', function () {
    $response = runJurisdiction($this->middleware, jurisdictionRequest('/api/user/family-members'));

    expect($response->getStatusCode())->toBe(200)->and($response->getContent())->toBe('OK');
});

it('passes through when the user holds the pack jurisdiction', function () {
    $response = runJurisdiction($this->middleware, jurisdictionRequest('/api/gb/dashboard', ['GB']));

    expect($response->getStatusCode())->toBe(200);
});

it('403s when the user holds jurisdictions but not this pack', function () {
    $response = runJurisdiction($this->middleware, jurisdictionRequest('/api/gb/dashboard', ['ZA']));

    expect($response->getStatusCode())->toBe(403)
        ->and(json_decode($response->getContent(), true)['code'])->toBe('JURISDICTION_NOT_AUTHORISED');
});

it('fail-opens for a row-less user (no jurisdictions)', function () {
    $response = runJurisdiction($this->middleware, jurisdictionRequest('/api/gb/dashboard', []));

    expect($response->getStatusCode())->toBe(200);
});

it('passes through an unauthenticated pack request', function () {
    $response = runJurisdiction($this->middleware, jurisdictionRequest('/api/gb/dashboard'));

    expect($response->getStatusCode())->toBe(200);
});
