<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Beneficiary nomination for a SA protection policy (WS 1.5b).
 *
 * The sum of allocation_percentage across all rows for a given policy
 * must equal 100.00 — enforced at the application layer
 * (StoreZaBeneficiariesRequest + controller transaction).
 */
class ZaProtectionBeneficiary extends Model
{
    use HasFactory;

    protected $table = 'za_protection_beneficiaries';

    protected $fillable = [
        'policy_id',
        'beneficiary_type',
        'name',
        'relationship',
        'allocation_percentage',
        'id_number',
        'is_dutiable',
    ];

    protected $casts = [
        'allocation_percentage' => 'decimal:2',
        'is_dutiable' => 'boolean',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(ZaProtectionPolicy::class, 'policy_id');
    }

    /**
     * Auto-set is_dutiable when beneficiary_type is assigned. Payable-to-estate
     * policies are dutiable under Estate Duty Act s3(3)(a)(ii). WS 1.6 Estate
     * will consume this flag.
     */
    public function setBeneficiaryTypeAttribute(string $value): void
    {
        $this->attributes['beneficiary_type'] = $value;
        $this->attributes['is_dutiable'] = ($value === 'estate');
    }

    protected static function newFactory()
    {
        return \Fynla\Packs\Za\Database\Factories\ZaProtectionBeneficiaryFactory::new();
    }
}
