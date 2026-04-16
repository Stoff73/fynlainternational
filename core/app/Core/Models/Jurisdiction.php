<?php

declare(strict_types=1);

namespace Fynla\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model for the jurisdictions table.
 *
 * Each row represents a country/territory that the application supports.
 * Jurisdictions are seeded (e.g. GB in Phase 0, ZA in Phase 2) and
 * linked to users via the user_jurisdictions pivot table.
 *
 * @property int    $id
 * @property string $code       ISO 3166-1 alpha-2 country code
 * @property string $name       Country name in English
 * @property string $currency   ISO 4217 currency code
 * @property string $locale     BCP 47 locale tag
 * @property bool   $active     Whether available for new users
 */
class Jurisdiction extends Model
{
    protected $table = 'jurisdictions';

    protected $fillable = [
        'code',
        'name',
        'currency',
        'locale',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Users assigned to this jurisdiction.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'user_jurisdictions',
            'jurisdiction_id',
            'user_id'
        )->withPivot(['is_primary', 'activated_at'])
         ->withTimestamps();
    }

    /**
     * Tax years defined for this jurisdiction.
     */
    public function taxYears(): HasMany
    {
        return $this->hasMany(TaxYear::class, 'jurisdiction_id');
    }

    /**
     * Find a jurisdiction by its ISO country code.
     */
    public static function byCode(string $code): ?self
    {
        return static::where('code', strtoupper($code))->first();
    }

    /**
     * Convert to the core Jurisdiction value object.
     */
    public function toValueObject(): \Fynla\Core\Jurisdiction\Jurisdiction
    {
        return new \Fynla\Core\Jurisdiction\Jurisdiction(
            code: $this->code,
            name: $this->name,
            currency: $this->currency,
            locale: $this->locale,
        );
    }
}
