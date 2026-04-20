<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert dc_pensions.pension_type ENUM → VARCHAR(60).
 *
 * The original UK ENUM (occupational|sipp|personal|stakeholder) is
 * UK-specific. SA funds use retirement_annuity / pension_fund /
 * provident_fund / preservation_fund. Future country packs will bring
 * their own fund type vocabularies. VARCHAR removes the DB-level
 * country-specific constraint; request-level validation stays
 * jurisdiction-aware via form requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dc_pensions')) {
            return;
        }

        DB::statement("ALTER TABLE dc_pensions MODIFY COLUMN pension_type VARCHAR(60) NOT NULL DEFAULT 'occupational'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('dc_pensions')) {
            return;
        }

        DB::statement("ALTER TABLE dc_pensions MODIFY COLUMN pension_type ENUM('occupational','sipp','personal','stakeholder') NOT NULL DEFAULT 'occupational'");
    }
};
