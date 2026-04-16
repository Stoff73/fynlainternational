<?php

declare(strict_types=1);

namespace Fynla\Core\Models;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for the tax_years table.
 *
 * Stores the start/end dates of each tax year per jurisdiction.
 * Different jurisdictions have different tax year boundaries
 * (GB: Apr 6 – Apr 5; ZA: Mar 1 – Feb 28; etc.).
 *
 * @property int    $id
 * @property int    $jurisdiction_id
 * @property string $label      E.g. "2025/26"
 * @property string $starts_on  First day of tax year (Y-m-d)
 * @property string $ends_on    Last day of tax year (Y-m-d)
 */
class TaxYear extends Model
{
    protected $table = 'tax_years';

    protected $fillable = [
        'jurisdiction_id',
        'label',
        'starts_on',
        'ends_on',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /**
     * The jurisdiction this tax year belongs to.
     */
    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    /**
     * Find the tax year for a given jurisdiction code and date.
     *
     * @param string                  $jurisdictionCode ISO 3166-1 alpha-2 code
     * @param \DateTimeInterface|null $date             Date to resolve (defaults to today)
     *
     * @return static|null The matching tax year, or null if none found
     */
    public static function resolveFor(string $jurisdictionCode, ?\DateTimeInterface $date = null): ?self
    {
        $date = $date ?? new DateTimeImmutable('today');
        $dateString = $date->format('Y-m-d');

        return static::query()
            ->whereHas('jurisdiction', function ($q) use ($jurisdictionCode) {
                $q->where('code', strtoupper($jurisdictionCode));
            })
            ->where('starts_on', '<=', $dateString)
            ->where('ends_on', '>=', $dateString)
            ->first();
    }

    /**
     * Convert to the core TaxYear value object.
     */
    public function toValueObject(): \Fynla\Core\TaxYear\TaxYear
    {
        return new \Fynla\Core\TaxYear\TaxYear(
            jurisdictionCode: $this->jurisdiction->code,
            label: $this->label,
            startsOn: new DateTimeImmutable($this->starts_on->format('Y-m-d')),
            endsOn: new DateTimeImmutable($this->ends_on->format('Y-m-d')),
        );
    }
}
