<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-Pot balance buckets per member per fund holding.
 *
 * One row per (user, fund_holding). The four balances update together:
 *   - vested_balance_minor: pre-2024-09-01 balances (old rules apply).
 *   - provident_vested_pre2021_balance_minor: sub-split of vested — the
 *     pre-1-March-2021 provident-fund portion for members 55+ on that
 *     date. Retains 100% commutability at retirement per spec § 9.1.
 *     Must never exceed vested_balance_minor. Consumed by WS 1.4b.
 *   - savings_balance_minor: 1/3 of post-2024-09-01 contributions;
 *     accessible once per tax year, min R2,000, taxed at marginal rate.
 *   - retirement_balance_minor: 2/3 of post-2024-09-01 contributions;
 *     locked until retirement, must buy compulsory annuity.
 *
 * fund_holding_id is a foreign key into the main-app `dc_pensions`
 * table. country_code='ZA' on the parent dc_pensions row distinguishes
 * SA holdings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_retirement_fund_buckets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('fund_holding_id')->constrained('dc_pensions')->cascadeOnDelete();
            $t->bigInteger('vested_balance_minor')->default(0);
            $t->bigInteger('provident_vested_pre2021_balance_minor')->default(0);
            $t->bigInteger('savings_balance_minor')->default(0);
            $t->bigInteger('retirement_balance_minor')->default(0);
            $t->string('balance_ccy', 3)->default('ZAR');
            $t->date('last_transaction_date')->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'fund_holding_id'], 'za_retirement_buckets_unique');
            $t->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_retirement_fund_buckets');
    }
};
