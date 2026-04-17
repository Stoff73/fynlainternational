<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tax_years', 'calendar_type')) {
            return;
        }

        Schema::table('tax_years', function (Blueprint $table) {
            $table->string('calendar_type', 16)
                ->default('tax_year')
                ->after('label')
                ->comment('tax_year | calendar_year | custom — per ADR-006');
        });

        // Backfill existing rows defensively. The column default covers new
        // inserts, but MySQL's ALTER … DEFAULT does not retroactively update
        // existing rows in every scenario.
        DB::table('tax_years')
            ->whereNull('calendar_type')
            ->update(['calendar_type' => 'tax_year']);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tax_years', 'calendar_type')) {
            return;
        }

        Schema::table('tax_years', function (Blueprint $table) {
            $table->dropColumn('calendar_type');
        });
    }
};
