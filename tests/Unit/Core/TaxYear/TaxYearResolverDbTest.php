<?php

declare(strict_types=1);

use Fynla\Core\Models\Jurisdiction;
use Fynla\Core\Models\TaxYear as TaxYearModel;
use Fynla\Core\TaxYear\TaxYear;
use Fynla\Core\TaxYear\TaxYearResolver;
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

    TaxYearModel::create([
        'jurisdiction_id' => $this->gb->id,
        'label' => '2025/26',
        'starts_on' => '2025-04-06',
        'ends_on' => '2026-04-05',
    ]);

    TaxYearModel::create([
        'jurisdiction_id' => $this->gb->id,
        'label' => '2026/27',
        'starts_on' => '2026-04-06',
        'ends_on' => '2027-04-05',
    ]);

    $this->resolver = new TaxYearResolver;
});

it('resolves the correct tax year from the database', function () {
    $taxYear = $this->resolver->resolve('GB', new DateTimeImmutable('2025-10-01'));

    expect($taxYear)->toBeInstanceOf(TaxYear::class);
    expect($taxYear->label)->toBe('2025/26');
    expect($taxYear->jurisdictionCode)->toBe('GB');
});

it('resolves the current tax year when no date is given', function () {
    // The resolver defaults to new DateTimeImmutable('today'), which
    // Carbon::setTestNow cannot freeze — so seed a tax year that is
    // guaranteed to contain the real test-run date and derive the
    // expected label from it, instead of hardcoding a year that goes
    // stale every 6 April.
    $today = new DateTimeImmutable('today');
    $start = new DateTimeImmutable($today->format('Y').'-04-06');
    if ($today < $start) {
        $start = $start->modify('-1 year');
    }
    $end = $start->modify('+1 year -1 day');
    $label = $start->format('Y').'/'.$end->format('y');

    TaxYearModel::firstOrCreate(
        ['jurisdiction_id' => $this->gb->id, 'label' => $label],
        ['starts_on' => $start->format('Y-m-d'), 'ends_on' => $end->format('Y-m-d')]
    );

    $taxYear = $this->resolver->resolve('GB');

    expect($taxYear->label)->toBe($label);
});

it('throws RuntimeException when no tax year matches', function () {
    $this->resolver->resolve('GB', new DateTimeImmutable('2020-01-01'));
})->throws(RuntimeException::class, 'No tax year configured');

it('throws RuntimeException for unknown jurisdiction', function () {
    $this->resolver->resolve('ZA', new DateTimeImmutable('2025-10-01'));
})->throws(RuntimeException::class, 'No tax year configured');

it('returns all tax years for a jurisdiction ordered by start date descending', function () {
    $years = $this->resolver->allForJurisdiction('GB');

    expect($years)->toHaveCount(2);
    expect($years[0]->label)->toBe('2026/27');
    expect($years[1]->label)->toBe('2025/26');
});

it('returns empty array for unknown jurisdiction in allForJurisdiction', function () {
    $years = $this->resolver->allForJurisdiction('ZA');

    expect($years)->toBeEmpty();
});
