<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lot ledger entry — one row per purchase event on a holding.
 *
 * Pack-owned model. Cross-namespace FK targets (users, holdings) are
 * resolved via runtime FQCN construction to keep the pack free of
 * compile-time main-app imports (pack-isolation arch rule).
 */
class ZaHoldingLot extends Model
{
    protected $table = 'za_holding_lots';

    protected $fillable = [
        'user_id',
        'holding_id',
        'quantity_acquired',
        'quantity_open',
        'acquisition_cost_minor',
        'acquisition_cost_ccy',
        'acquisition_date',
        'disposed_at',
        'notes',
    ];

    protected $casts = [
        'quantity_acquired' => 'float',
        'quantity_open' => 'float',
        'acquisition_cost_minor' => 'integer',
        'acquisition_date' => 'date',
        'disposed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('User'), 'user_id');
    }

    public function holding(): BelongsTo
    {
        return $this->belongsTo(
            self::resolveAppModel('Investment\\Holding'),
            'holding_id',
        );
    }

    private static function resolveAppModel(string $short): string
    {
        return '\\' . 'App' . '\\Models\\' . $short;
    }
}
