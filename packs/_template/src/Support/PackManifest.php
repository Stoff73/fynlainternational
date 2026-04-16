<?php

declare(strict_types=1);

namespace Fynla\Packs\XX\Support;

/**
 * Pack metadata value object.
 *
 * Provides a static descriptor used by the PackRegistry to discover
 * and configure this country pack at runtime.
 */
class PackManifest
{
    /**
     * Return the pack descriptor array for registry registration.
     *
     * @return array{code: string, name: string, currency: string, locale: string, table_prefix: string, navigation: array, routes: array}
     */
    public static function describe(): array
    {
        return [
            'code' => 'xx',
            'name' => '<Country>',
            'currency' => 'XXX',
            'locale' => 'en-XX',
            'table_prefix' => 'xx_',
            'navigation' => [],
            'routes' => [],
        ];
    }
}
