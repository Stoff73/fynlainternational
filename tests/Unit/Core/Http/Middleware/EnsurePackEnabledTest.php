<?php

declare(strict_types=1);

use Fynla\Core\Http\Middleware\EnsurePackEnabled;
use Fynla\Core\Registry\PackManifest;
use Fynla\Core\Registry\PackRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

describe('EnsurePackEnabled Middleware', function () {
    beforeEach(function () {
        $this->registry = new PackRegistry();
        $this->middleware = new EnsurePackEnabled($this->registry);
    });

    it('passes through when pack is enabled', function () {
        $this->registry->register(new PackManifest(
            code: 'GB',
            name: 'United Kingdom',
            currency: 'GBP',
            locale: 'en_GB',
            tablePrefix: 'gb_',
        ));

        $request = Request::create('/api/protection', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK', 200),
            'gb',
        );

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toBe('OK');
    });

    it('returns 404 when pack is not enabled', function () {
        // Registry is empty — no packs registered
        $request = Request::create('/api/protection', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK', 200),
            'za',
        );

        expect($response->getStatusCode())->toBe(404);

        $data = json_decode($response->getContent(), true);
        expect($data['code'])->toBe('PACK_NOT_AVAILABLE');
        expect($data['error'])->toBe('Pack not available');
    });

    it('normalises lowercase pack code to uppercase', function () {
        $this->registry->register(new PackManifest(
            code: 'ZA',
            name: 'South Africa',
            currency: 'ZAR',
            locale: 'en_ZA',
            tablePrefix: 'za_',
        ));

        $request = Request::create('/api/test', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK', 200),
            'za', // lowercase
        );

        expect($response->getStatusCode())->toBe(200);
    });

    it('returns 404 for registered pack with different code', function () {
        $this->registry->register(new PackManifest(
            code: 'GB',
            name: 'United Kingdom',
            currency: 'GBP',
            locale: 'en_GB',
            tablePrefix: 'gb_',
        ));

        $request = Request::create('/api/test', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK', 200),
            'za',
        );

        expect($response->getStatusCode())->toBe(404);
    });
});
