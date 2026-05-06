<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * R-4 (UK pack relocation): backfill polymorphic *_type columns from the
 * legacy App\Models\X namespace to the relocated Fynla\Packs\Gb\Models\X
 * namespace.
 *
 * Affected tables:
 *   - joint_account_logs.loggable_type — Property, Mortgage,
 *     InvestmentAccount, SavingsAccount.
 *   - holdings.holdable_type — Investment\InvestmentAccount, DCPension.
 *
 * Pre-relocation values like 'App\Models\Property' point at a class that
 * no longer exists; without this backfill, Eloquent's morphTo lookup
 * raises ClassNotFound on every read of an affected row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $jointMap = [
            'App\\Models\\Property' => 'Fynla\\Packs\\Gb\\Models\\Property',
            'App\\Models\\Mortgage' => 'Fynla\\Packs\\Gb\\Models\\Mortgage',
            'App\\Models\\InvestmentAccount' => 'Fynla\\Packs\\Gb\\Models\\Investment\\InvestmentAccount',
            'App\\Models\\Investment\\InvestmentAccount' => 'Fynla\\Packs\\Gb\\Models\\Investment\\InvestmentAccount',
            'App\\Models\\SavingsAccount' => 'Fynla\\Packs\\Gb\\Models\\SavingsAccount',
        ];
        foreach ($jointMap as $legacy => $relocated) {
            DB::table('joint_account_logs')
                ->where('loggable_type', $legacy)
                ->update(['loggable_type' => $relocated]);
        }

        $holdableMap = [
            'App\\Models\\Investment\\InvestmentAccount' => 'Fynla\\Packs\\Gb\\Models\\Investment\\InvestmentAccount',
            'App\\Models\\InvestmentAccount' => 'Fynla\\Packs\\Gb\\Models\\Investment\\InvestmentAccount',
            'App\\Models\\DCPension' => 'Fynla\\Packs\\Gb\\Models\\DCPension',
        ];
        foreach ($holdableMap as $legacy => $relocated) {
            DB::table('holdings')
                ->where('holdable_type', $legacy)
                ->update(['holdable_type' => $relocated]);
        }
    }

    public function down(): void
    {
        // No down migration: reverting to the legacy namespace would require
        // restoring App\Models\X classes that were removed in this branch.
    }
};
