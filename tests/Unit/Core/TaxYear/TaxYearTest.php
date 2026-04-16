<?php

declare(strict_types=1);

use Fynla\Core\TaxYear\TaxYear;

describe('TaxYear', function () {
    it('constructs with jurisdiction code, label, start and end dates', function () {
        $taxYear = new TaxYear(
            jurisdictionCode: 'GB',
            label: '2025/26',
            startsOn: new DateTimeImmutable('2025-04-06'),
            endsOn: new DateTimeImmutable('2026-04-05'),
        );

        expect($taxYear->jurisdictionCode)->toBe('GB');
        expect($taxYear->label)->toBe('2025/26');
        expect($taxYear->startsOn->format('Y-m-d'))->toBe('2025-04-06');
        expect($taxYear->endsOn->format('Y-m-d'))->toBe('2026-04-05');
    });

    it('correctly identifies a date within the tax year', function () {
        $taxYear = new TaxYear(
            jurisdictionCode: 'GB',
            label: '2025/26',
            startsOn: new DateTimeImmutable('2025-04-06'),
            endsOn: new DateTimeImmutable('2026-04-05'),
        );

        // Mid-year date
        expect($taxYear->contains(new DateTimeImmutable('2025-09-15')))->toBeTrue();

        // Start boundary (inclusive)
        expect($taxYear->contains(new DateTimeImmutable('2025-04-06')))->toBeTrue();

        // End boundary (inclusive)
        expect($taxYear->contains(new DateTimeImmutable('2026-04-05')))->toBeTrue();
    });

    it('correctly identifies a date outside the tax year', function () {
        $taxYear = new TaxYear(
            jurisdictionCode: 'GB',
            label: '2025/26',
            startsOn: new DateTimeImmutable('2025-04-06'),
            endsOn: new DateTimeImmutable('2026-04-05'),
        );

        // Day before start
        expect($taxYear->contains(new DateTimeImmutable('2025-04-05')))->toBeFalse();

        // Day after end
        expect($taxYear->contains(new DateTimeImmutable('2026-04-06')))->toBeFalse();

        // Completely outside
        expect($taxYear->contains(new DateTimeImmutable('2024-01-01')))->toBeFalse();
    });

    it('detects overlapping tax years', function () {
        $gb2025 = new TaxYear(
            jurisdictionCode: 'GB',
            label: '2025/26',
            startsOn: new DateTimeImmutable('2025-04-06'),
            endsOn: new DateTimeImmutable('2026-04-05'),
        );

        // Overlapping: South Africa tax year March-Feb overlaps with UK April-April
        $zaOverlap = new TaxYear(
            jurisdictionCode: 'ZA',
            label: '2025/26',
            startsOn: new DateTimeImmutable('2025-03-01'),
            endsOn: new DateTimeImmutable('2026-02-28'),
        );

        expect($gb2025->overlaps($zaOverlap))->toBeTrue();
        expect($zaOverlap->overlaps($gb2025))->toBeTrue();
    });

    it('detects non-overlapping tax years', function () {
        $gb2024 = new TaxYear(
            jurisdictionCode: 'GB',
            label: '2024/25',
            startsOn: new DateTimeImmutable('2024-04-06'),
            endsOn: new DateTimeImmutable('2025-04-05'),
        );

        $gb2025 = new TaxYear(
            jurisdictionCode: 'GB',
            label: '2025/26',
            startsOn: new DateTimeImmutable('2025-04-06'),
            endsOn: new DateTimeImmutable('2026-04-05'),
        );

        expect($gb2024->overlaps($gb2025))->toBeFalse();
        expect($gb2025->overlaps($gb2024))->toBeFalse();
    });

    it('detects adjacent tax years as non-overlapping', function () {
        // UK tax year ends 5 April, next starts 6 April — no overlap
        $year1 = new TaxYear(
            jurisdictionCode: 'GB',
            label: '2024/25',
            startsOn: new DateTimeImmutable('2024-04-06'),
            endsOn: new DateTimeImmutable('2025-04-05'),
        );

        $year2 = new TaxYear(
            jurisdictionCode: 'GB',
            label: '2025/26',
            startsOn: new DateTimeImmutable('2025-04-06'),
            endsOn: new DateTimeImmutable('2026-04-05'),
        );

        expect($year1->overlaps($year2))->toBeFalse();
    });

    it('works with calendar-year jurisdictions', function () {
        $us2025 = new TaxYear(
            jurisdictionCode: 'US',
            label: '2025',
            startsOn: new DateTimeImmutable('2025-01-01'),
            endsOn: new DateTimeImmutable('2025-12-31'),
        );

        expect($us2025->contains(new DateTimeImmutable('2025-06-15')))->toBeTrue();
        expect($us2025->contains(new DateTimeImmutable('2024-12-31')))->toBeFalse();
    });
});
