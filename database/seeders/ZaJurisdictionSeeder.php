<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the South Africa jurisdiction row. Prerequisite for the ZA tax
 * configuration seeder — ZaTaxConfigurationSeeder calls this first.
 *
 * Idempotent via DB::updateOrInsert keyed on the ISO code.
 */
class ZaJurisdictionSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('jurisdictions')->updateOrInsert(
            ['code' => 'ZA'],
            [
                'name' => 'South Africa',
                'currency' => 'ZAR',
                'locale' => 'en-ZA',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
