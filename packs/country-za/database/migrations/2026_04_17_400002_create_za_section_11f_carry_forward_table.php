<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workstream 1.1 FR-M6 — SA Section 11F carry-forward ledger.
 *
 * Tracks the unused portion of each member's retirement-contribution
 * deduction when annual contributions exceed the lesser of 27.5% of
 * remuneration/taxable income or R350,000. ZaTaxEngine is a pure
 * calculator — it accepts prior carry-forward as a parameter.
 * ZaSection11fTracker owns persistence.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('za_section_11f_carry_forward')) {
            return;
        }

        Schema::create('za_section_11f_carry_forward', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tax_year', 9)
                ->comment('Tax year this carry-forward balance applies to (e.g. "2026/27")');
            $table->bigInteger('carry_forward_cents')
                ->default(0)
                ->comment('Unused Section 11F deduction carried forward, in minor units');
            $table->timestamps();

            $table->unique(['user_id', 'tax_year'], 'za_section_11f_user_year_unique');
            $table->index('user_id', 'za_section_11f_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_section_11f_carry_forward');
    }
};
