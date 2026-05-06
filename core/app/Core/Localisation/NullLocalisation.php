<?php

declare(strict_types=1);

namespace Fynla\Core\Localisation;

use Fynla\Core\Contracts\Localisation;

/**
 * Sentinel Localisation implementation used while a pack does not
 * yet supply a real one. Returns conservative defaults (GBP-shaped
 * formatting, ISO date) so callers never receive null and the
 * application doesn't crash on resolution.
 *
 * Always replaced by a pack-specific implementation before any
 * jurisdiction goes user-visible.
 */
final class NullLocalisation implements Localisation
{
    public function currencyCode(): string
    {
        return 'XXX';
    }

    public function currencySymbol(): string
    {
        return '¤';
    }

    public function locale(): string
    {
        return 'en';
    }

    public function dateFormat(): string
    {
        return 'Y-m-d';
    }

    public function formatMoney(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, '.', ',');
    }

    public function getTerminology(): array
    {
        return [];
    }
}
