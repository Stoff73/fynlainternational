<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workstream 1.1 FR-M6 — SA Section 10C tax-free annuity pool ledger.
 *
 * Records the running total of non-deductible retirement-fund
 * contributions that become tax-free when drawn down as annuity income
 * (Section 10C ITA). ZaSection10cTracker owns the pool; the engine
 * consumes the running balance as a parameter.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('za_section_10c_ledger')) {
            return;
        }

        Schema::create('za_section_10c_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('contribution_date')
                ->comment('Date the non-deductible contribution was made');
            $table->bigInteger('non_deductible_amount_cents')
                ->comment('Portion not deductible in the contribution year, added to the pool');
            $table->bigInteger('running_pool_cents')
                ->comment('Cumulative Section 10C pool as at this entry (minor units)');
            $table->timestamps();

            $table->index('user_id', 'za_section_10c_user_idx');
            $table->index(['user_id', 'contribution_date'], 'za_section_10c_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_section_10c_ledger');
    }
};
