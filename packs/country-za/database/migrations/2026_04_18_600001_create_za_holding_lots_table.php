<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only lot ledger for SA CGT base-cost tracking.
 *
 * One row per purchase event. Sells draw down weighted-average cost basis
 * across all open lots for the same holding; disposed_at marks lots that
 * have been fully liquidated (partial disposals decrement quantity_open
 * without setting disposed_at).
 *
 * Currency: always ZAR for v1. Offshore holdings in foreign currency
 * require FX translation at the caller; Eighth Schedule average/spot-rate
 * rules are deferred to Phase 2 cross-border.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_holding_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('holding_id')->constrained('holdings')->cascadeOnDelete();
            $t->decimal('quantity_acquired', 18, 8);
            $t->decimal('quantity_open', 18, 8);
            $t->bigInteger('acquisition_cost_minor');
            $t->string('acquisition_cost_ccy', 3)->default('ZAR');
            $t->date('acquisition_date');
            $t->timestamp('disposed_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'holding_id'], 'za_lots_user_holding_idx');
            $t->index('acquisition_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_holding_lots');
    }
};
