<?php

declare(strict_types=1);

use Fynla\Core\Models\Jurisdiction;
use Fynla\Core\Models\TaxYear;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gb = Jurisdiction::create([
        'code' => 'GB',
        'name' => 'United Kingdom',
        'currency' => 'GBP',
        'locale' => 'en-GB',
        'active' => true,
    ]);

    TaxYear::create([
        'jurisdiction_id' => $this->gb->id,
        'label' => '2024/25',
        'starts_on' => '2024-04-06',
        'ends_on' => '2025-04-05',
    ]);

    TaxYear::create([
        'jurisdiction_id' => $this->gb->id,
        'label' => '2025/26',
        'starts_on' => '2025-04-06',
        'ends_on' => '2026-04-05',
    ]);

    TaxYear::create([
        'jurisdiction_id' => $this->gb->id,
        'label' => '2026/27',
        'starts_on' => '2026-04-06',
        'ends_on' => '2027-04-05',
    ]);
});

it('resolves the correct tax year for a date within boundaries', function () {
    $taxYear = TaxYear::resolveFor('GB', new DateTimeImmutable('2025-06-15'));

    expect($taxYear)->not->toBeNull();
    expect($taxYear->label)->toBe('2025/26');
});

it('resolves the tax year at exact start boundary', function () {
    $taxYear = TaxYear::resolveFor('GB', new DateTimeImmutable('2025-04-06'));

    expect($taxYear)->not->toBeNull();
    expect($taxYear->label)->toBe('2025/26');
});

it('resolves the tax year at exact end boundary', function () {
    $taxYear = TaxYear::resolveFor('GB', new DateTimeImmutable('2026-04-05'));

    expect($taxYear)->not->toBeNull();
    expect($taxYear->label)->toBe('2025/26');
});

it('returns null when no tax year matches the date', function () {
    $taxYear = TaxYear::resolveFor('GB', new DateTimeImmutable('2020-01-01'));

    expect($taxYear)->toBeNull();
});

it('returns null for unknown jurisdiction code', function () {
    $taxYear = TaxYear::resolveFor('ZA', new DateTimeImmutable('2025-06-15'));

    expect($taxYear)->toBeNull();
});

it('converts to a value object', function () {
    $model = TaxYear::resolveFor('GB', new DateTimeImmutable('2025-06-15'));
    $vo = $model->toValueObject();

    expect($vo)->toBeInstanceOf(\Fynla\Core\TaxYear\TaxYear::class);
    expect($vo->jurisdictionCode)->toBe('GB');
    expect($vo->label)->toBe('2025/26');
    expect($vo->startsOn->format('Y-m-d'))->toBe('2025-04-06');
    expect($vo->endsOn->format('Y-m-d'))->toBe('2026-04-05');
});

it('belongs to a jurisdiction', function () {
    $taxYear = TaxYear::resolveFor('GB', new DateTimeImmutable('2025-06-15'));

    expect($taxYear->jurisdiction)->not->toBeNull();
    expect($taxYear->jurisdiction->code)->toBe('GB');
});
