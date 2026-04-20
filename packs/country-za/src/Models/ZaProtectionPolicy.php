<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ZA protection policy (WS 1.5b).
 *
 * Pack-owned model; cross-namespace FK targets (users) resolved via
 * runtime FQCN to keep the pack free of compile-time main-app imports.
 *
 * product_type discriminates across six SA product categories per
 * ZaProtectionEngine::getAvailablePolicyTypes().
 */
class ZaProtectionPolicy extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'za_protection_policies';

    protected $fillable = [
        'user_id',
        'joint_owner_id',
        'ownership_percentage',
        'product_type',
        'provider',
        'policy_number',
        'cover_amount_minor',
        'premium_amount_minor',
        'premium_frequency',
        'start_date',
        'end_date',
        'severity_tier',
        'waiting_period_months',
        'benefit_term_months',
        'group_scheme',
        'notes',
    ];

    protected $casts = [
        'ownership_percentage' => 'decimal:2',
        'cover_amount_minor' => 'integer',
        'premium_amount_minor' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'waiting_period_months' => 'integer',
        'benefit_term_months' => 'integer',
        'group_scheme' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('User'), 'user_id');
    }

    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('User'), 'joint_owner_id');
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(ZaProtectionBeneficiary::class, 'policy_id');
    }

    protected static function newFactory()
    {
        return \Fynla\Packs\Za\Database\Factories\ZaProtectionPolicyFactory::new();
    }

    private static function resolveAppModel(string $short): string
    {
        return '\\' . 'App' . '\\Models\\' . $short;
    }
}
