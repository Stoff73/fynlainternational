<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R-15 (UK pack relocation): backfill polymorphic *_type columns from the
 * legacy App\Models\User namespace to the relocated Fynla\Core\Models\User
 * namespace.
 *
 * R-14b-vii relocated User to core and R-14b-viii registered a
 * class_alias(\Fynla\Core\Models\User::class, 'App\\Models\\User') in
 * CoreServiceProvider::boot() as a read-side safety fallback. This migration
 * makes the data canonical so the class_alias becomes dead code.
 *
 * Pre-deploy prod row counts (2026-05-11):
 *   - personal_access_tokens.tokenable_type = 370
 *   - notifications.notifiable_type         = 9
 *   - audit_logs.model_type                 = 0  (already clean)
 *
 * Idempotent: WHERE = legacy value, so re-runs no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = 'App\\Models\\User';
        $canonical = 'Fynla\\Core\\Models\\User';

        $targets = [
            ['personal_access_tokens', 'tokenable_type'],
            ['notifications', 'notifiable_type'],
            ['audit_logs', 'model_type'],
        ];

        foreach ($targets as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            DB::table($table)
                ->where($column, $legacy)
                ->update([$column => $canonical]);
        }
    }

    public function down(): void
    {
        // No down migration: reverting would require restoring the legacy
        // App\Models\User class that was removed in R-14b-vii.
    }
};
