<?php

declare(strict_types=1);

namespace Fynla\Core\Models;

use App\Models\User;
use Fynla\Core\Contracts\PackAssetRepository;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Household extends Model
{
    use HasFactory;

    protected $fillable = [
        'household_name',
        'notes',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /**
     * Aggregated asset summaries across every pack-owned, household-
     * scoped surface (properties, business interests, chattels, cash
     * accounts, investment accounts, trusts — and equivalent SA
     * surfaces once they ship).
     *
     * Pre-R-14b-vi this was six pack-namespaced hasMany methods on the
     * Household model (Property, BusinessInterest, Chattel, CashAccount,
     * InvestmentAccount, Trust). The six relation methods were never
     * read at any call site, so they were dropped in favour of a single
     * contract-routed accessor — keeps core free of pack literals while
     * preserving the household-aggregate read surface for future
     * consumers.
     */
    public function getHouseholdAssetsAttribute(): Collection
    {
        return app(PackAssetRepository::class)->householdAssets((int) $this->id);
    }
}
