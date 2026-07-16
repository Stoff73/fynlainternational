<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Query;

use Fynla\Core\Contracts\PackUserRelationProvider;
use Fynla\Packs\Za\Models\ZaDonation;
use Fynla\Packs\Za\Models\ZaExchangeControlEntry;
use Fynla\Packs\Za\Models\ZaHoldingLot;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Fynla\Packs\Za\Models\ZaReg28Snapshot;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Fynla\Packs\Za\Models\ZaTfsaContribution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * SA user-scoped relation provider (WS2). Maps SA-owned relation-type tags to
 * their models so core can resolve a user's SA per-module relations, mirroring
 * GbPackUserRelationProvider. GB-backed ZA surfaces (savings/investment tagged
 * country_code='ZA') resolve through the GB provider via the core composite.
 */
final class ZaPackUserRelationProvider implements PackUserRelationProvider
{
    /**
     * @return array<string, class-string<Model>>
     */
    private static function classMap(): array
    {
        return [
            'za.protection_policy' => ZaProtectionPolicy::class,
            'za.retirement_fund_bucket' => ZaRetirementFundBucket::class,
            'za.tfsa_contribution' => ZaTfsaContribution::class,
            'za.holding_lot' => ZaHoldingLot::class,
            'za.donation' => ZaDonation::class,
            'za.exchange_control_entry' => ZaExchangeControlEntry::class,
            'za.reg28_snapshot' => ZaReg28Snapshot::class,
        ];
    }

    public function modelClassFor(string $relationType): ?string
    {
        return self::classMap()[$relationType] ?? null;
    }

    public function userRelatedModels(int $userId, string $relationType): Collection
    {
        $modelClass = $this->modelClassFor($relationType);

        if ($modelClass === null) {
            return new Collection();
        }

        return $modelClass::query()->where('user_id', $userId)->get();
    }

    public function userRelatedModel(int $userId, string $relationType): ?Model
    {
        $modelClass = $this->modelClassFor($relationType);

        if ($modelClass === null) {
            return null;
        }

        return $modelClass::query()->where('user_id', $userId)->first();
    }
}
