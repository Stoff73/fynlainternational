<?php

declare(strict_types=1);

use Fynla\Core\Registry\PackRegistry;

it('binds pack.gb.tax to the UK TaxConfigService', function () {
    $instance = app('pack.gb.tax');

    expect($instance)->toBeInstanceOf(\App\Services\TaxConfigService::class);
});

it('registers GB with the PackRegistry', function () {
    /** @var PackRegistry $registry */
    $registry = app(PackRegistry::class);

    expect($registry->isEnabled('gb'))->toBeTrue();
    expect($registry->byCountryCode('gb')->code)->toBe('gb');
    expect($registry->byCountryCode('gb')->currency)->toBe('GBP');
});

it('resolves pack.gb.* for every contract class that exists today', function () {
    // Only the 5 bindings whose target classes currently exist.
    // The remaining 4 (localisation, identity, banking, life_tables) are
    // documented contract gaps — see the commit message for GbPackServiceProvider.
    // They'll close as the equivalent UK classes are extracted during or after
    // Phase 1.8.
    $keys = [
        'pack.gb.tax',
        'pack.gb.retirement',
        'pack.gb.investment',
        'pack.gb.protection',
        'pack.gb.estate',
    ];

    foreach ($keys as $key) {
        expect(app()->bound($key))->toBeTrue("Expected {$key} to be bound");
    }
});
