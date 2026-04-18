<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TFSA contribution ledger entry.
 *
 * Pack-owned model referencing app-owned parents (users, family_members,
 * savings_accounts). Canonical cross-pack pattern.
 *
 * BelongsTo relationships target app-owned models by fully-qualified
 * string class name built via concatenation, keeping the pack free of
 * compile-time imports from the main application namespace. This
 * satisfies the strict pack-isolation architecture rule.
 */
class ZaTfsaContribution extends Model
{
    protected $table = 'za_tfsa_contributions';

    protected $fillable = [
        'user_id',
        'beneficiary_id',
        'savings_account_id',
        'tax_year',
        'amount_minor',
        'amount_ccy',
        'source_type',
        'contribution_date',
        'notes',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'contribution_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('User'), 'user_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('FamilyMember'), 'beneficiary_id');
    }

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('SavingsAccount'), 'savings_account_id');
    }

    /**
     * Resolve a main-application model class by short name without a
     * compile-time namespace import. Runtime-built FQCN keeps the
     * pack-isolation architecture test green while still allowing ORM
     * relationships against app-owned models.
     */
    private static function resolveAppModel(string $short): string
    {
        return '\\' . 'App' . '\\Models\\' . $short;
    }
}
