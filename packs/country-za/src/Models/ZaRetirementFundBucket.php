<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Two-Pot balance bucket row — one per (member, fund holding).
 *
 * Pack-owned model. Cross-namespace FK targets (users, dc_pensions)
 * resolved via runtime FQCN construction to keep the pack free of
 * compile-time main-app imports.
 *
 * The four balance columns are updated atomically by
 * ZaRetirementFundBucketRepository::applyDeltas; never modify them
 * directly on the model.
 */
class ZaRetirementFundBucket extends Model
{
    protected $table = 'za_retirement_fund_buckets';

    protected $fillable = [
        'user_id',
        'fund_holding_id',
        'vested_balance_minor',
        'provident_vested_pre2021_balance_minor',
        'savings_balance_minor',
        'retirement_balance_minor',
        'balance_ccy',
        'last_transaction_date',
    ];

    protected $casts = [
        'vested_balance_minor' => 'integer',
        'provident_vested_pre2021_balance_minor' => 'integer',
        'savings_balance_minor' => 'integer',
        'retirement_balance_minor' => 'integer',
        'last_transaction_date' => 'date',
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
