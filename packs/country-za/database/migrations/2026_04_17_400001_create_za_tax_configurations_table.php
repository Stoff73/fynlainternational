<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workstream 1.1 FR-M4 — normalised tax-configuration storage for SA.
 *
 * Stores SARS 2026/27 values as minor-unit integers, one row per key.
 * This pattern (vs the UK's whole-pound JSON blob) is ADR-005 compliant
 * from day 1 and avoids per-year schema migrations when SARS publishes
 * new brackets: seeding a new `tax_year` + its rows is enough.
 *
 * Key shape: dot-path ("income_tax.brackets.4.accumulated_base",
 * "rebates.primary", "retirement.lump_sum.retirement_table.1.rate"). The
 * seeder drives the keys; readers grab them via ZaTaxConfigService::get().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('za_tax_configurations')) {
            return;
        }

        Schema::create('za_tax_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('tax_year', 9)
                ->comment('e.g. "2026/27"');
            $table->string('key_path', 255)
                ->comment('Dot-path identifier (e.g. "income_tax.brackets.0.rate")');
            $table->bigInteger('value_cents')
                ->comment('Value in minor units (cents). Rates stored as basis points (e.g. 36% = 3600).');
            $table->date('effective_from')
                ->comment('First date this value applies (SA tax year starts 1 March)');
            $table->string('notes', 255)
                ->nullable()
                ->comment('Optional source citation — kept short (< 200 chars) for audit trail');
            $table->timestamps();

            $table->unique(['tax_year', 'key_path'], 'za_tax_configurations_year_key_unique');
            $table->index('tax_year', 'za_tax_configurations_year_idx');
            $table->index('effective_from', 'za_tax_configurations_effective_from_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_tax_configurations');
    }
};
