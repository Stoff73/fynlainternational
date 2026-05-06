<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Support;

/**
 * GB pack metadata accessor.
 *
 * Mirrors ZA's PackManifest helper — a thin wrapper around the
 * core PackManifest value object, exposing pack-level constants
 * for use within the pack itself.
 */
final class PackManifest
{
    public const CODE = 'gb';
    public const NAME = 'United Kingdom';
    public const CURRENCY = 'GBP';
    public const LOCALE = 'en_GB';

    /**
     * Table prefix is empty during Phase 1 — UK tables keep their
     * historical unprefixed names (per architecture-plan-v3.md § 7,
     * decision deferred to a follow-up workstream after Phase 1).
     */
    public const TABLE_PREFIX = '';
}
