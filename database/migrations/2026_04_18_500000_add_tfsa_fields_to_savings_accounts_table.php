<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add SA Tax-Free Savings Account (TFSA) fields alongside existing UK ISA
 * fields. The country_code column (from WS 0.6) determines which set of
 * fields applies per row.
 *
 * All _minor columns use signed bigInteger to match the WS 0.6 shadow-
 * column migration pattern. _ccy companion columns are added for
 * cross-border aggregation consistency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_accounts', function (Blueprint $t) {
            $t->boolean('is_tfsa')->default(false)->after('isa_subscription_amount');
            $t->string('tfsa_subscription_year', 10)->nullable()->after('is_tfsa');
            $t->bigInteger('tfsa_subscription_amount_minor')->nullable()
                ->after('tfsa_subscription_year');
            $t->string('tfsa_subscription_amount_ccy', 3)->nullable()
                ->after('tfsa_subscription_amount_minor');
            $t->bigInteger('tfsa_lifetime_contributed_minor')->nullable()
                ->after('tfsa_subscription_amount_ccy');
            $t->string('tfsa_lifetime_contributed_ccy', 3)->nullable()
                ->after('tfsa_lifetime_contributed_minor');
        });
    }

    public function down(): void
    {
        Schema::table('savings_accounts', function (Blueprint $t) {
            $t->dropColumn([
                'is_tfsa',
                'tfsa_subscription_year',
                'tfsa_subscription_amount_minor',
                'tfsa_subscription_amount_ccy',
                'tfsa_lifetime_contributed_minor',
                'tfsa_lifetime_contributed_ccy',
            ]);
        });
    }
};
