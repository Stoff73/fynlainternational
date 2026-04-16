<?php

declare(strict_types=1);

use Fynla\Core\Registry\PackManifest;

describe('PackManifest', function () {
    it('constructs with all required properties', function () {
        $manifest = new PackManifest(
            code: 'GB',
            name: 'United Kingdom',
            currency: 'GBP',
            locale: 'en_GB',
            tablePrefix: 'gb_',
            navigation: [['label' => 'Dashboard', 'route' => '/gb/dashboard']],
            routes: ['routes/gb.php'],
        );

        expect($manifest->code)->toBe('GB');
        expect($manifest->name)->toBe('United Kingdom');
        expect($manifest->currency)->toBe('GBP');
        expect($manifest->locale)->toBe('en_GB');
        expect($manifest->tablePrefix)->toBe('gb_');
        expect($manifest->navigation)->toBe([['label' => 'Dashboard', 'route' => '/gb/dashboard']]);
        expect($manifest->routes)->toBe(['routes/gb.php']);
    });

    it('creates from array via static factory', function () {
        $data = [
            'code' => 'ZA',
            'name' => 'South Africa',
            'currency' => 'ZAR',
            'locale' => 'en_ZA',
            'table_prefix' => 'za_',
            'navigation' => [['label' => 'Home']],
            'routes' => ['routes/za.php'],
        ];

        $manifest = PackManifest::fromArray($data);

        expect($manifest->code)->toBe('ZA');
        expect($manifest->name)->toBe('South Africa');
        expect($manifest->currency)->toBe('ZAR');
        expect($manifest->locale)->toBe('en_ZA');
        expect($manifest->tablePrefix)->toBe('za_');
        expect($manifest->navigation)->toBe([['label' => 'Home']]);
        expect($manifest->routes)->toBe(['routes/za.php']);
    });

    it('defaults navigation and routes to empty arrays', function () {
        $manifest = PackManifest::fromArray([
            'code' => 'GB',
            'name' => 'United Kingdom',
            'currency' => 'GBP',
            'locale' => 'en_GB',
            'table_prefix' => 'gb_',
        ]);

        expect($manifest->navigation)->toBe([]);
        expect($manifest->routes)->toBe([]);
    });

    it('exposes all properties as readonly', function () {
        $manifest = new PackManifest(
            code: 'GB',
            name: 'United Kingdom',
            currency: 'GBP',
            locale: 'en_GB',
            tablePrefix: 'gb_',
        );

        $reflection = new ReflectionClass($manifest);

        foreach (['code', 'name', 'currency', 'locale', 'tablePrefix', 'navigation', 'routes'] as $property) {
            $prop = $reflection->getProperty($property);
            expect($prop->isReadOnly())->toBeTrue("Expected {$property} to be readonly");
        }
    });
});
