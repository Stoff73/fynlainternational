<?php

declare(strict_types=1);

namespace Fynla\Core\TaxYear;

use DateTimeImmutable;
use DateTimeInterface;
use Fynla\Core\Models\TaxYear as TaxYearModel;

/**
 * Resolves the current (or specific) tax year for a jurisdiction.
 *
 * Queries the tax_years database table to look up boundaries
 * by jurisdiction code and date.
 */
class TaxYearResolver
{
    /**
     * Resolve the tax year for a jurisdiction at a given date.
     *
     * If no date is provided, resolves for the current date.
     * Returns the tax year whose boundaries contain the given date.
     *
     * @param string                 $jurisdictionCode ISO 3166-1 alpha-2 code
     * @param DateTimeInterface|null $date             Date to resolve for (defaults to now)
     *
     * @return TaxYear The resolved tax year
     *
     * @throws \RuntimeException If no tax year is configured for the jurisdiction/date combination
     */
    public function resolve(string $jurisdictionCode, ?DateTimeInterface $date = null): TaxYear
    {
        $date = $date ?? new DateTimeImmutable('today');

        $model = TaxYearModel::resolveFor($jurisdictionCode, $date);

        if ($model === null) {
            $dateStr = $date->format('Y-m-d');
            throw new \RuntimeException(
                "No tax year configured for jurisdiction '{$jurisdictionCode}' on date {$dateStr}. "
                . 'Ensure tax_years table is seeded for this jurisdiction.'
            );
        }

        return $model->toValueObject();
    }

    /**
     * Get all tax years for a jurisdiction, ordered by start date descending.
     *
     * @param string $jurisdictionCode ISO 3166-1 alpha-2 code
     *
     * @return array<int, TaxYear> List of tax year value objects
     */
    public function allForJurisdiction(string $jurisdictionCode): array
    {
        $models = TaxYearModel::query()
            ->with('jurisdiction')
            ->whereHas('jurisdiction', function ($q) use ($jurisdictionCode) {
                $q->where('code', strtoupper($jurisdictionCode));
            })
            ->orderByDesc('starts_on')
            ->get();

        return $models->map(fn (TaxYearModel $m) => $m->toValueObject())->all();
    }
}
