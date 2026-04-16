<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxConfiguration extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tax_year',
        'effective_from',
        'effective_to',
        'config_data',
        'is_active',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'config_data' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the active tax configuration.
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Get tax configuration by tax year.
     */
    public static function getByTaxYear(string $taxYear): ?self
    {
        return static::where('tax_year', $taxYear)->first();
    }

    /**
     * Activate this configuration and deactivate others.
     */
    public function activate(): void
    {
        static::where('is_active', true)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }
}
