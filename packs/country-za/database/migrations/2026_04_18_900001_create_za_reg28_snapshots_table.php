<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historical Reg 28 compliance snapshots.
 *
 * One row per compliance check. Stores the asset-allocation breakdown
 * (as JSON) plus boolean flags for each limit category. The overall
 * `compliant` flag is true iff all per-class flags are true.
 *
 * fund_holding_id is nullable — a null row is a portfolio-wide
 * roll-up across all of a user's SA retirement holdings; a non-null
 * row is a per-holding snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_reg28_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('fund_holding_id')->nullable()
                ->constrained('dc_pensions')->cascadeOnDelete();
            $t->date('as_at_date');
            $t->json('allocation');
            $t->boolean('offshore_compliant')->default(true);
            $t->boolean('equity_compliant')->default(true);
            $t->boolean('property_compliant')->default(true);
            $t->boolean('private_equity_compliant')->default(true);
            $t->boolean('commodities_compliant')->default(true);
            $t->boolean('hedge_funds_compliant')->default(true);
            $t->boolean('other_compliant')->default(true);
            $t->boolean('single_entity_compliant')->default(true);
            $t->boolean('compliant')->default(true);
            $t->json('breaches')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'as_at_date'], 'za_reg28_user_date_idx');
            $t->index('fund_holding_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_reg28_snapshots');
    }
};
