<?php

declare(strict_types=1);

use Fynla\Core\Registry\PackManifest;
use Fynla\Core\Registry\PackRegistry;

describe('PackRegistry', function () {
    beforeEach(function () {
        $this->registry = new PackRegistry();
    });

    it('registers a pack manifest successfully', function () {
        $manifest = new PackManifest(
            code: 'GB',
            name: 'United Kingdom',
            currency: 'GBP',
            locale: 'en_GB',
            tablePrefix: 'gb_',
        );

        $this->registry->register($manifest);

        expect($this->registry->isEnabled('GB'))->toBeTrue();
        expect($this->registry->count())->toBe(1);
    });

    it('prevents duplicate registration', function () {
        $manifest = new PackManifest(
            code: 'GB',
            name: 'United Kingdom',
            currency: 'GBP',
            locale: 'en_GB',
            tablePrefix: 'gb_',
        );

        $this->registry->register($manifest);

        expect(fn () => $this->registry->register($manifest))
            ->toThrow(RuntimeException::class, "Country pack 'GB' (United Kingdom) is already registered.");
    });

    it('lists all enabled packs', function () {
        $gb = new PackManifest(code: 'GB', name: 'United Kingdom', currency: 'GBP', locale: 'en_GB', tablePrefix: 'gb_');
        $za = new PackManifest(code: 'ZA', name: 'South Africa', currency: 'ZAR', locale: 'en_ZA', tablePrefix: 'za_');

        $this->registry->register($gb);
        $this->registry->register($za);

        $enabled = $this->registry->listEnabled();

        expect($enabled)->toHaveCount(2);
        expect($enabled)->toHaveKeys(['GB', 'ZA']);
        expect($enabled['GB'])->toBe($gb);
        expect($enabled['ZA'])->toBe($za);
    });

    it('finds a pack by country code', function () {
        $manifest = new PackManifest(
            code: 'ZA',
            name: 'South Africa',
            currency: 'ZAR',
            locale: 'en_ZA',
            tablePrefix: 'za_',
        );

        $this->registry->register($manifest);

        $found = $this->registry->byCountryCode('ZA');

        expect($found)->toBe($manifest);
        expect($found->code)->toBe('ZA');
        expect($found->name)->toBe('South Africa');
    });

    it('returns null for unknown country code', function () {
        expect($this->registry->byCountryCode('XX'))->toBeNull();
    });

    it('reports correct count', function () {
        expect($this->registry->count())->toBe(0);

        $this->registry->register(new PackManifest(code: 'GB', name: 'United Kingdom', currency: 'GBP', locale: 'en_GB', tablePrefix: 'gb_'));
        expect($this->registry->count())->toBe(1);

        $this->registry->register(new PackManifest(code: 'ZA', name: 'South Africa', currency: 'ZAR', locale: 'en_ZA', tablePrefix: 'za_'));
        expect($this->registry->count())->toBe(2);
    });

    it('reports correct enabled status', function () {
        $this->registry->register(new PackManifest(code: 'GB', name: 'United Kingdom', currency: 'GBP', locale: 'en_GB', tablePrefix: 'gb_'));

        expect($this->registry->isEnabled('GB'))->toBeTrue();
        expect($this->registry->isEnabled('ZA'))->toBeFalse();
    });

    it('returns registered country codes', function () {
        $this->registry->register(new PackManifest(code: 'GB', name: 'United Kingdom', currency: 'GBP', locale: 'en_GB', tablePrefix: 'gb_'));
        $this->registry->register(new PackManifest(code: 'ZA', name: 'South Africa', currency: 'ZAR', locale: 'en_ZA', tablePrefix: 'za_'));

        $codes = $this->registry->codes();

        expect($codes)->toBe(['GB', 'ZA']);
    });
});
