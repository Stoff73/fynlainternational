<?php

declare(strict_types=1);

namespace Fynla\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for the user_jurisdictions pivot table.
 *
 * Links a user to a jurisdiction. A user may have 0..n jurisdictions;
 * exactly one should be marked as primary.
 *
 * @property int         $id
 * @property int         $user_id
 * @property int         $jurisdiction_id
 * @property bool        $is_primary
 * @property string|null $activated_at
 */
class UserJurisdiction extends Model
{
    protected $table = 'user_jurisdictions';

    protected $fillable = [
        'user_id',
        'jurisdiction_id',
        'is_primary',
        'activated_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'activated_at' => 'datetime',
    ];

    /**
     * The user this assignment belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * The jurisdiction this assignment points to.
     */
    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }
}
