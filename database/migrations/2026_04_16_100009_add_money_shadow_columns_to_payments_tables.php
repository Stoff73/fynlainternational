<?php

declare(strict_types=1);

/**
 * Shadow columns for money internationalisation — Group 9: Payments & Other
 *
 * Tables: payments (1 column), subscriptions (1),
 *         recommendation_tracking (1), business_interests (5)
 * Total: 8 money columns -> 16 shadow columns (_minor bigInteger + _ccy char(3))
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->bigInteger('amount_minor')->nullable()->after('amount');
            $table->char('amount_ccy', 3)->nullable()->after('amount_minor');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->bigInteger('amount_minor')->nullable()->after('amount');
            $table->char('amount_ccy', 3)->nullable()->after('amount_minor');
        });

        Schema::table('recommendation_tracking', function (Blueprint $table) {
            $table->bigInteger('recommended_amount_minor')->nullable()->after('recommended_amount');
            $table->char('recommended_amount_ccy', 3)->nullable()->after('recommended_amount_minor');
        });

        Schema::table('business_interests', function (Blueprint $table) {
            $table->bigInteger('acquisition_cost_minor')->nullable()->after('acquisition_cost');
            $table->char('acquisition_cost_ccy', 3)->nullable()->after('acquisition_cost_minor');
            $table->bigInteger('current_valuation_minor')->nullable()->after('current_valuation');
            $table->char('current_valuation_ccy', 3)->nullable()->after('current_valuation_minor');
            $table->bigInteger('annual_revenue_minor')->nullable()->after('annual_revenue');
            $table->char('annual_revenue_ccy', 3)->nullable()->after('annual_revenue_minor');
            $table->bigInteger('annual_profit_minor')->nullable()->after('annual_profit');
            $table->char('annual_profit_ccy', 3)->nullable()->after('annual_profit_minor');
            $table->bigInteger('annual_dividend_income_minor')->nullable()->after('annual_dividend_income');
            $table->char('annual_dividend_income_ccy', 3)->nullable()->after('annual_dividend_income_minor');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['amount_minor', 'amount_ccy']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['amount_minor', 'amount_ccy']);
        });

        Schema::table('recommendation_tracking', function (Blueprint $table) {
            $table->dropColumn(['recommended_amount_minor', 'recommended_amount_ccy']);
        });

        Schema::table('business_interests', function (Blueprint $table) {
            $table->dropColumn([
                'acquisition_cost_minor', 'acquisition_cost_ccy',
                'current_valuation_minor', 'current_valuation_ccy',
                'annual_revenue_minor', 'annual_revenue_ccy',
                'annual_profit_minor', 'annual_profit_ccy',
                'annual_dividend_income_minor', 'annual_dividend_income_ccy',
            ]);
        });
    }
};
