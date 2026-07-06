<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Fynla\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lifetime donation record (SA Estate v1 slice 2).
 *
 * Pack-owned model referencing the app-owned users table. Feeds the SARS
 * donations-tax cumulative aggregation (since 2018-03-01) and the estate-duty
 * lifetime-donations context.
 */
class ZaDonation extends Model
{
    protected $table = 'za_donations';

    protected $fillable = [
        'user_id',
        'amount_minor',
        'amount_ccy',
        'donation_date',
        'recipient',
        'is_exempt',
        'notes',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'donation_date' => 'date',
        'is_exempt' => 'boolean',
    ];

    /** SARS donations-tax cumulative anchor. */
    public const CUMULATIVE_ANCHOR = '2018-03-01';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
