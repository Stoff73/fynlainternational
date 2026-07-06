<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lifetime donations register (SA Estate v1 slice 2).
 *
 * SARS aggregates donations tax cumulatively since 1 March 2018, so the
 * register keeps every donation with its date; the donations-tax calculation
 * filters and sums from that anchor. amount_minor is signed bigInteger to
 * match the WS 0.6 shadow-column pattern; amount_ccy is always 'ZAR'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_donations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->bigInteger('amount_minor');
            $t->string('amount_ccy', 3)->default('ZAR');
            $t->date('donation_date');
            $t->string('recipient', 255)->nullable();
            $t->boolean('is_exempt')->default(false);
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'donation_date'], 'za_donations_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_donations');
    }
};
