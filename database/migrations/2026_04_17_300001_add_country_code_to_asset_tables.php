<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workstream 0.6 — location-based jurisdiction detection.
 *
 * Adds a nullable CHAR(2) country_code column to every asset-bearing table.
 * The JurisdictionDetectionObserver watches create/update events on the
 * owning models and auto-activates a jurisdiction when a non-primary
 * country_code appears.
 *
 * Also extends user_jurisdictions with deactivated_at (soft-deactivation on
 * last-asset delete) and auto_detected (distinguishes user-initiated from
 * asset-driven activation).
 *
 * Tables targeted (6):
 *   - investment_accounts
 *   - dc_pensions
 *   - db_pensions
 *   - savings_accounts
 *   - properties
 *   - assets           (estate-level chattels/gifts — users never see the name)
 */
return new class extends Migration
{
    private const ASSET_TABLES = [
        'investment_accounts',
        'dc_pensions',
        'db_pensions',
        'savings_accounts',
        'properties',
        'assets',
    ];

    public function up(): void
    {
        foreach (self::ASSET_TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'country_code')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->char('country_code', 2)
                    ->nullable()
                    ->comment('ISO 3166-1 alpha-2 country where the asset is held');
                $t->index('country_code', "{$table}_country_code_idx");
            });
        }

        // Extend the pivot with the soft-deactivation + auto-detection
        // columns the observer needs.
        if (Schema::hasTable('user_jurisdictions')) {
            Schema::table('user_jurisdictions', function (Blueprint $t) {
                if (! Schema::hasColumn('user_jurisdictions', 'deactivated_at')) {
                    $t->timestamp('deactivated_at')
                        ->nullable()
                        ->after('activated_at')
                        ->comment('When this jurisdiction was soft-deactivated (last foreign asset removed)');
                }
                if (! Schema::hasColumn('user_jurisdictions', 'auto_detected')) {
                    $t->boolean('auto_detected')
                        ->default(false)
                        ->after('deactivated_at')
                        ->comment('True when activation came from JurisdictionDetectionObserver, not user action');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::ASSET_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'country_code')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex("{$table}_country_code_idx");
                $t->dropColumn('country_code');
            });
        }

        if (Schema::hasTable('user_jurisdictions')) {
            Schema::table('user_jurisdictions', function (Blueprint $t) {
                if (Schema::hasColumn('user_jurisdictions', 'auto_detected')) {
                    $t->dropColumn('auto_detected');
                }
                if (Schema::hasColumn('user_jurisdictions', 'deactivated_at')) {
                    $t->dropColumn('deactivated_at');
                }
            });
        }
    }
};
