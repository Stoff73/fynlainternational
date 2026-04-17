<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Support;

/**
 * Metadata for the South Africa country pack.
 *
 * The tax year boundary is NOT carried on the manifest — ADR-006 specifies
 * that tax-year start/end lives in the `tax_years` table and is resolved
 * at runtime by Fynla\Core\TaxYear\TaxYearResolver. SA uses a March-to-
 * February tax year; SDA/FIA allowances follow a separate calendar-year
 * cadence stored alongside via the `calendar_type` column.
 */
class PackManifest
{
    /**
     * @return array{code: string, name: string, currency: string, locale: string, table_prefix: string, navigation: array, routes: array}
     */
    public static function describe(): array
    {
        return [
            'code' => 'za',
            'name' => 'South Africa',
            'currency' => 'ZAR',
            'locale' => 'en_ZA',
            'table_prefix' => 'za_',
            'navigation' => [],
            'routes' => [],
        ];
    }
}
