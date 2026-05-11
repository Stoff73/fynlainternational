<?php

declare(strict_types=1);

namespace Fynla\Core\Query;

/**
 * Immutable summary of a single jurisdiction-owned asset, returned by
 * PackAssetRepository implementations.
 *
 * Type-tagged so a composite repository can merge results from multiple
 * packs without each pack needing to know about the others' concrete
 * model classes. `valueMinor` is in minor currency units (pence/cents)
 * per ADR-005; consumers convert back to pounds at the display boundary.
 *
 * The shape intentionally mirrors the read-only fields that cross-module
 * consumers (net-worth aggregators, holistic-plan builders, dashboard
 * cards) actually need. Concrete pack models retain their full Eloquent
 * surface and are reachable via PackAssetResolver::resolveAccount() when
 * a caller needs more than this summary exposes.
 */
final class AssetSummary
{
    /**
     * @param int         $id                   Primary key on the pack's concrete model
     * @param string      $type                 Pack-scoped type tag (e.g. "gb.investment_account", "gb.savings_account", "gb.property", "gb.business_interest", "gb.chattel", "gb.dc_pension", "gb.db_pension", "gb.mortgage")
     * @param string      $name                 Display label for the asset
     * @param int         $valueMinor           Current value in minor currency units (pence)
     * @param string      $currency             ISO 4217 currency code (e.g. "GBP", "ZAR")
     * @param int         $userId               Primary owner's user id
     * @param int|null    $jointOwnerId         Joint owner's user id, if any
     * @param float       $ownershipPercentage  Primary owner's share (0–100); spouse share is 100 - this
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $name,
        public readonly int $valueMinor,
        public readonly string $currency,
        public readonly int $userId,
        public readonly ?int $jointOwnerId,
        public readonly float $ownershipPercentage,
    ) {
    }
}
