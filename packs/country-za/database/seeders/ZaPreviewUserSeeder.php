<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Database\Seeders;

use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Models\DCPension;
use Fynla\Packs\Za\Models\ZaExchangeControlEntry;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Fynla\Packs\Za\Models\ZaTfsaContribution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds one South African preview persona with cross-module SA financial data
 * (SA Estate/Goals/Coordination demo + local E2E). Idempotent on the persona id.
 *
 * NOTE: the persona IS selectable from the landing-page persona selector —
 * it is wired end-to-end (PreviewController::VALID_PERSONAS + PERSONA_METADATA,
 * frontend preview.js PERSONA_DATA/PERSONA_ORDER) and holds a primary ZA
 * user_jurisdictions row (WS1), so it exercises the full SA session flow
 * including WS3 localisation.
 */
class ZaPreviewUserSeeder extends Seeder
{
    private const PERSONA_ID = 'sa_professional';

    public function run(): void
    {
        // Jurisdiction must exist first.
        $this->call(ZaJurisdictionSeeder::class);
        $jurisdictionId = (int) DB::table('jurisdictions')->where('code', 'ZA')->value('id');

        $user = User::withoutEvents(function () {
            $user = User::firstOrNew([
                'is_preview_user' => true,
                'preview_persona_id' => self::PERSONA_ID,
            ]);
            $user->first_name = 'Thabo';
            $user->surname = 'Nkosi';
            $user->email = 'preview_'.self::PERSONA_ID.'@fynla.local';
            $user->password = Hash::make(Str::random(32));
            $user->date_of_birth = '1985-06-15';
            $user->marital_status = 'married';
            $user->employment_status = 'employed';
            $user->occupation = 'Software Engineer';
            $user->employer = 'Takealot';
            $user->annual_employment_income = 960_000; // R960k
            $user->target_retirement_age = 60;
            $user->monthly_expenditure = 35_000;
            $user->save();

            return $user;
        });

        // Attach ZA as the primary jurisdiction (idempotent).
        DB::table('user_jurisdictions')->updateOrInsert(
            ['user_id' => $user->id, 'jurisdiction_id' => $jurisdictionId],
            ['is_primary' => true, 'activated_at' => now(), 'auto_detected' => false, 'updated_at' => now(), 'created_at' => now()],
        );

        // Fresh SA financial data (delete-then-insert keeps the seed idempotent).
        ZaRetirementFundBucket::where('user_id', $user->id)->delete();
        ZaTfsaContribution::where('user_id', $user->id)->delete();
        ZaExchangeControlEntry::where('user_id', $user->id)->delete();
        ZaProtectionPolicy::where('user_id', $user->id)->delete();
        DCPension::where('user_id', $user->id)->where('country_code', 'ZA')->delete();

        $fund = DCPension::create([
            'user_id' => $user->id,
            'pension_type' => 'retirement_annuity',
            'scheme_type' => 'personal',
            'provider' => 'Allan Gray',
            'country_code' => 'ZA',
        ]);

        ZaRetirementFundBucket::create([
            'user_id' => $user->id,
            'fund_holding_id' => $fund->id,
            'vested_balance_minor' => 800_000_00,
            'provident_vested_pre2021_balance_minor' => 0,
            'savings_balance_minor' => 120_000_00,
            'retirement_balance_minor' => 680_000_00,
            'balance_ccy' => 'ZAR',
            'last_transaction_date' => now()->subMonth(),
        ]);

        ZaTfsaContribution::create([
            'user_id' => $user->id,
            'tax_year' => $this->currentZaTaxYear(),
            'amount_minor' => 36_000_00,
            'amount_ccy' => 'ZAR',
            'source_type' => 'contribution',
            'contribution_date' => now()->subMonths(2),
        ]);

        ZaExchangeControlEntry::create([
            'user_id' => $user->id,
            'allowance_type' => 'sda',
            'amount_minor' => 250_000_00,
            'amount_ccy' => 'ZAR',
            'calendar_year' => (int) now()->format('Y'),
            'transfer_date' => now()->subMonths(3),
        ]);

        ZaProtectionPolicy::create([
            'user_id' => $user->id,
            'product_type' => 'life',
            'provider' => 'Discovery Life',
            'cover_amount_minor' => 5_000_000_00,
            'premium_amount_minor' => 1_500_00,
            'premium_frequency' => 'monthly',
            'start_date' => now()->subYear(),
        ]);
    }

    private function currentZaTaxYear(): string
    {
        $now = now();
        $startYear = $now->month >= 3 ? $now->year : $now->year - 1;

        return sprintf('%d/%02d', $startYear, ($startYear + 1) % 100);
    }
}
