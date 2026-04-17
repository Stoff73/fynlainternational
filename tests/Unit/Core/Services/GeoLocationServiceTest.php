<?php

declare(strict_types=1);

use Fynla\Core\Services\GeoLocationService;
use Illuminate\Http\Request;

beforeEach(function () {
    // Scrub any GEO_OVERRIDE the ambient shell might leak into the test,
    // so env-fallback cases don't accidentally resolve.
    putenv('GEO_OVERRIDE');
});

afterEach(function () {
    putenv('GEO_OVERRIDE');
});

describe('CF-IPCountry header priority', function () {
    it('returns the country from a Cloudflare header', function () {
        $request = Request::create('/');
        $request->headers->set('CF-IPCountry', 'GB');

        $service = new GeoLocationService;

        expect($service->countryFromRequest($request))->toBe('GB');
    });

    it('uppercases and trims lower-cased or padded header values', function () {
        $request = Request::create('/');
        $request->headers->set('CF-IPCountry', '  za  ');

        $service = new GeoLocationService;

        expect($service->countryFromRequest($request))->toBe('ZA');
    });

    it('treats XX and T1 as unknown, falling through', function () {
        $service = new GeoLocationService;

        $unknown = Request::create('/');
        $unknown->headers->set('CF-IPCountry', 'XX');

        $tor = Request::create('/');
        $tor->headers->set('CF-IPCountry', 'T1');

        expect($service->countryFromRequest($unknown))->toBeNull();
        expect($service->countryFromRequest($tor))->toBeNull();
    });
});

describe('MaxMind reader fallback', function () {
    it('uses the injected reader when no CF header is present', function () {
        $reader = fn (string $ip): ?string => $ip === '102.68.0.1' ? 'ZA' : null;
        $service = new GeoLocationService($reader);

        $request = Request::create('/', server: ['REMOTE_ADDR' => '102.68.0.1']);

        expect($service->countryFromRequest($request))->toBe('ZA');
    });

    it('prefers CF header over the MaxMind reader', function () {
        // The reader would say ZA but Cloudflare says GB — CF wins.
        $reader = fn (): ?string => 'ZA';
        $service = new GeoLocationService($reader);

        $request = Request::create('/', server: ['REMOTE_ADDR' => '8.8.8.8']);
        $request->headers->set('CF-IPCountry', 'GB');

        expect($service->countryFromRequest($request))->toBe('GB');
    });

    it('swallows reader exceptions and returns null instead of crashing', function () {
        $reader = fn (): ?string => throw new RuntimeException('DB file missing');
        $service = new GeoLocationService($reader);

        $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);

        expect($service->countryFromRequest($request))->toBeNull();
    });

    it('returns null for unknown IPs the reader cannot resolve', function () {
        $reader = fn (): ?string => null;
        $service = new GeoLocationService($reader);

        $request = Request::create('/', server: ['REMOTE_ADDR' => '0.0.0.0']);

        expect($service->countryFromRequest($request))->toBeNull();
    });
});

describe('GEO_OVERRIDE env fallback', function () {
    it('uses GEO_OVERRIDE when no header and no reader resolve', function () {
        putenv('GEO_OVERRIDE=za');

        $service = new GeoLocationService;
        $request = Request::create('/');

        expect($service->countryFromRequest($request))->toBe('ZA');
    });

    it('returns null when nothing resolves', function () {
        $service = new GeoLocationService;
        $request = Request::create('/');

        expect($service->countryFromRequest($request))->toBeNull();
    });
});
