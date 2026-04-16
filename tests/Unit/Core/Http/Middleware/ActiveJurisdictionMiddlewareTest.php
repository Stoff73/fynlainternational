<?php

declare(strict_types=1);

use Fynla\Core\Http\Middleware\ActiveJurisdictionMiddleware;
use Fynla\Core\Registry\PackManifest;
use Fynla\Core\Registry\PackRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;

describe('ActiveJurisdictionMiddleware', function () {
    beforeEach(function () {
        $this->registry = new PackRegistry();
        $this->middleware = new ActiveJurisdictionMiddleware($this->registry);

        $this->gbManifest = new PackManifest(
            code: 'GB',
            name: 'United Kingdom',
            currency: 'GBP',
            locale: 'en_GB',
            tablePrefix: 'gb_',
        );
    });

    it('passes through when no cc route parameter exists', function () {
        $request = Request::create('/api/core/health', 'GET');

        // Set up a route without a {cc} parameter — must be bound to avoid LogicException
        $route = new Route('GET', '/api/core/health', fn () => null);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle($request, fn () => new Response('OK', 200));

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toBe('OK');
    });

    it('returns 404 when pack is not registered', function () {
        $request = Request::create('/api/xx/dashboard', 'GET');

        $route = new Route('GET', '/api/{cc}/dashboard', fn () => null);
        $route->bind($request);
        $route->setParameter('cc', 'XX');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle($request, fn () => new Response('OK', 200));

        expect($response->getStatusCode())->toBe(404);

        $data = json_decode($response->getContent(), true);
        expect($data['code'])->toBe('PACK_NOT_FOUND');
    });

    it('returns 403 when user lacks jurisdiction entitlement', function () {
        $this->registry->register($this->gbManifest);

        $request = Request::create('/api/gb/dashboard', 'GET');

        $route = new Route('GET', '/api/{cc}/dashboard', fn () => null);
        $route->bind($request);
        $route->setParameter('cc', 'GB');
        $request->setRouteResolver(fn () => $route);

        // Create a mock authenticated user
        $user = new stdClass();
        $user->id = 1;
        $request->setUserResolver(fn () => $user);

        // Set env to only allow ZA, not GB
        putenv('FYNLA_ACTIVE_PACKS=ZA');

        $response = $this->middleware->handle($request, fn () => new Response('OK', 200));

        expect($response->getStatusCode())->toBe(403);

        $data = json_decode($response->getContent(), true);
        expect($data['code'])->toBe('JURISDICTION_NOT_AUTHORISED');

        // Clean up env
        putenv('FYNLA_ACTIVE_PACKS');
    });

    it('passes through when pack is registered and user has entitlement', function () {
        $this->registry->register($this->gbManifest);

        $request = Request::create('/api/gb/dashboard', 'GET');

        $route = new Route('GET', '/api/{cc}/dashboard', fn () => null);
        $route->bind($request);
        $route->setParameter('cc', 'GB');
        $request->setRouteResolver(fn () => $route);

        // Create a mock authenticated user
        $user = new stdClass();
        $user->id = 1;
        $request->setUserResolver(fn () => $user);

        // Set env to allow GB
        putenv('FYNLA_ACTIVE_PACKS=GB,ZA');

        $response = $this->middleware->handle($request, fn () => new Response('OK', 200));

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toBe('OK');

        // Clean up env
        putenv('FYNLA_ACTIVE_PACKS');
    });

    it('passes through for unauthenticated user when pack is registered', function () {
        $this->registry->register($this->gbManifest);

        $request = Request::create('/api/gb/dashboard', 'GET');

        $route = new Route('GET', '/api/{cc}/dashboard', fn () => null);
        $route->bind($request);
        $route->setParameter('cc', 'GB');
        $request->setRouteResolver(fn () => $route);

        // No user resolver set — unauthenticated request
        $response = $this->middleware->handle($request, fn () => new Response('OK', 200));

        expect($response->getStatusCode())->toBe(200);
    });

    it('normalises lowercase country code to uppercase', function () {
        $this->registry->register($this->gbManifest);

        $request = Request::create('/api/gb/dashboard', 'GET');

        $route = new Route('GET', '/api/{cc}/dashboard', fn () => null);
        $route->bind($request);
        $route->setParameter('cc', 'gb'); // lowercase
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle($request, fn () => new Response('OK', 200));

        expect($response->getStatusCode())->toBe(200);
    });
});
