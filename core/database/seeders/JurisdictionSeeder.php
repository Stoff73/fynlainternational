<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JurisdictionSeeder extends Seeder
{
    public function run(): void
    {
        // GB — United Kingdom (launch jurisdiction)
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

        // ZA — South Africa will be added in Phase 2
    }
}
