<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Exchange control ledger entry — one row per cross-border transfer.
 *
 * Pack-owned model. Cross-namespace FK target (users) resolved via
 * runtime FQCN construction to keep the pack free of compile-time
 * main-app imports.
 */
class ZaExchangeControlEntry extends Model
{
    protected $table = 'za_exchange_control_ledger';

    protected $fillable = [
        'user_id',
        'calendar_year',
        'allowance_type',
        'amount_minor',
        'amount_ccy',
        'destination_country',
        'purpose',
        'authorised_dealer',
        'recipient_account',
        'ait_reference',
        'ait_documents',
        'transfer_date',
        'notes',
    ];

    protected $casts = [
        'calendar_year' => 'integer',
        'amount_minor' => 'integer',
        'ait_documents' => 'array',
        'transfer_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('User'), 'user_id');
    }

    private static function resolveAppModel(string $short): string
    {
        return '\\' . 'App' . '\\Models\\' . $short;
    }
}
