<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reg 28 compliance snapshot row.
 *
 * Pack-owned model. Cross-namespace FK targets resolved via runtime
 * FQCN construction to keep the pack free of compile-time main-app
 * imports.
 */
class ZaReg28Snapshot extends Model
{
    protected $table = 'za_reg28_snapshots';

    protected $fillable = [
        'user_id', 'fund_holding_id', 'as_at_date', 'allocation',
        'offshore_compliant', 'equity_compliant', 'property_compliant',
        'private_equity_compliant', 'commodities_compliant',
        'hedge_funds_compliant', 'other_compliant', 'single_entity_compliant',
        'compliant', 'breaches',
    ];

    protected $casts = [
        'as_at_date' => 'date',
        'allocation' => 'array',
        'breaches' => 'array',
        'offshore_compliant' => 'boolean',
        'equity_compliant' => 'boolean',
        'property_compliant' => 'boolean',
        'private_equity_compliant' => 'boolean',
        'commodities_compliant' => 'boolean',
        'hedge_funds_compliant' => 'boolean',
        'other_compliant' => 'boolean',
        'single_entity_compliant' => 'boolean',
        'compliant' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('User'), 'user_id');
    }

    public function fundHolding(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('DCPension'), 'fund_holding_id');
    }

    private static function resolveAppModel(string $short): string
    {
        return '\\' . 'App' . '\\Models\\' . $short;
    }
}
