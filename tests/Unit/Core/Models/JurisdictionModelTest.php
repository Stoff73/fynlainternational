<?php

declare(strict_types=1);

use Fynla\Core\Models\Jurisdiction;
use Fynla\Core\Models\TaxYear;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed jurisdiction for tests
    Jurisdiction::create([
        'code' => 'GB',
        'name' => 'United Kingdom',
        'currency' => 'GBP',
        'locale' => 'en-GB',
        'active' => true,
    ]);
});

it('can find a jurisdiction by code', function () {
    $jurisdiction = Jurisdiction::byCode('GB');

    expect($jurisdiction)->not->toBeNull();
    expect($jurisdiction->code)->toBe('GB');
    expect($jurisdiction->name)->toBe('United Kingdom');
    expect($jurisdiction->currency)->toBe('GBP');
});

it('byCode is case-insensitive', function () {
    expect(Jurisdiction::byCode('gb'))->not->toBeNull();
    expect(Jurisdiction::byCode('Gb'))->not->toBeNull();
});

it('returns null for unknown jurisdiction code', function () {
    expect(Jurisdiction::byCode('XX'))->toBeNull();
});

it('converts to a value object', function () {
    $model = Jurisdiction::byCode('GB');
    $vo = $model->toValueObject();

    expect($vo)->toBeInstanceOf(\Fynla\Core\Jurisdiction\Jurisdiction::class);
    expect($vo->code)->toBe('GB');
    expect($vo->currency)->toBe('GBP');
    expect($vo->locale)->toBe('en-GB');
});

it('casts active as boolean', function () {
    $jurisdiction = Jurisdiction::byCode('GB');

    expect($jurisdiction->active)->toBeBool();
    expect($jurisdiction->active)->toBeTrue();
});

it('has tax years relationship', function () {
    $jurisdiction = Jurisdiction::byCode('GB');

    TaxYear::create([
        'jurisdiction_id' => $jurisdiction->id,
        'label' => '2025/26',
        'starts_on' => '2025-04-06',
        'ends_on' => '2026-04-05',
    ]);

    expect($jurisdiction->taxYears)->toHaveCount(1);
    expect($jurisdiction->taxYears->first()->label)->toBe('2025/26');
});
