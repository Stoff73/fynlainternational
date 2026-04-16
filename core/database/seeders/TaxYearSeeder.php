<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxYearSeeder extends Seeder
{
    public function run(): void
    {
        $gb = DB::table('jurisdictions')->where('code', 'GB')->first();

        if (! $gb) {
            $this->command->error('GB jurisdiction not found — run JurisdictionSeeder first.');

            return;
        }

        // UK tax year: 6 April to 5 April
        $ukTaxYears = [
            ['start' => 2020, 'end' => 2021],
            ['start' => 2021, 'end' => 2022],
            ['start' => 2022, 'end' => 2023],
            ['start' => 2023, 'end' => 2024],
            ['start' => 2024, 'end' => 2025],
            ['start' => 2025, 'end' => 2026],
            ['start' => 2026, 'end' => 2027],
        ];

        foreach ($ukTaxYears as $year) {
            $label = $year['start'] . '/' . substr((string) $year['end'], 2);

            DB::table('tax_years')->updateOrInsert(
                [
                    'jurisdiction_id' => $gb->id,
                    'label' => $label,
                ],
                [
                    'starts_on' => $year['start'] . '-04-06',
                    'ends_on' => $year['end'] . '-04-05',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // SA tax years (1 March to 28/29 February) will be added in Phase 2
    }
}
