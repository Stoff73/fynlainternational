<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the GB (United Kingdom) jurisdiction row.
 *
 * Idempotent via DB::updateOrInsert keyed on the ISO code.
 * Pairs with ZaJurisdictionSeeder which seeds South Africa.
 */
class JurisdictionSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('jurisdictions')->updateOrInsert(
            ['code' => 'GB'],
            [
                'name' => 'United Kingdom',
                'currency' => 'GBP',
                'locale' => 'en-GB',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
