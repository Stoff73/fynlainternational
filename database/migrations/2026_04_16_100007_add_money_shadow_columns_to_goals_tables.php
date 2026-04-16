<?php

declare(strict_types=1);

/**
 * Shadow columns for money internationalisation — Group 7: Goals & Events
 *
 * Tables: goals (6 columns), goal_contributions (2), goal_savings_account (1),
 *         life_events (1), life_event_allocations (2)
 * Total: 12 money columns -> 24 shadow columns (_minor bigInteger + _ccy char(3))
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->bigInteger('target_amount_minor')->nullable()->after('target_amount');
            $table->char('target_amount_ccy', 3)->nullable()->after('target_amount_minor');
            $table->bigInteger('current_amount_minor')->nullable()->after('current_amount');
            $table->char('current_amount_ccy', 3)->nullable()->after('current_amount_minor');
            $table->bigInteger('monthly_contribution_minor')->nullable()->after('monthly_contribution');
            $table->char('monthly_contribution_ccy', 3)->nullable()->after('monthly_contribution_minor');
            $table->bigInteger('estimated_property_price_minor')->nullable()->after('estimated_property_price');
            $table->char('estimated_property_price_ccy', 3)->nullable()->after('estimated_property_price_minor');
            $table->bigInteger('stamp_duty_estimate_minor')->nullable()->after('stamp_duty_estimate');
            $table->char('stamp_duty_estimate_ccy', 3)->nullable()->after('stamp_duty_estimate_minor');
            $table->bigInteger('additional_costs_estimate_minor')->nullable()->after('additional_costs_estimate');
            $table->char('additional_costs_estimate_ccy', 3)->nullable()->after('additional_costs_estimate_minor');
        });

        Schema::table('goal_contributions', function (Blueprint $table) {
            $table->bigInteger('amount_minor')->nullable()->after('amount');
            $table->char('amount_ccy', 3)->nullable()->after('amount_minor');
            $table->bigInteger('goal_balance_after_minor')->nullable()->after('goal_balance_after');
            $table->char('goal_balance_after_ccy', 3)->nullable()->after('goal_balance_after_minor');
        });

        Schema::table('goal_savings_account', function (Blueprint $table) {
            $table->bigInteger('allocated_amount_minor')->nullable()->after('allocated_amount');
            $table->char('allocated_amount_ccy', 3)->nullable()->after('allocated_amount_minor');
        });

        Schema::table('life_events', function (Blueprint $table) {
            $table->bigInteger('amount_minor')->nullable()->after('amount');
            $table->char('amount_ccy', 3)->nullable()->after('amount_minor');
        });

        Schema::table('life_event_allocations', function (Blueprint $table) {
            $table->bigInteger('suggested_amount_minor')->nullable()->after('suggested_amount');
            $table->char('suggested_amount_ccy', 3)->nullable()->after('suggested_amount_minor');
            $table->bigInteger('amount_minor')->nullable()->after('amount');
            $table->char('amount_ccy', 3)->nullable()->after('amount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn([
                'target_amount_minor', 'target_amount_ccy',
                'current_amount_minor', 'current_amount_ccy',
                'monthly_contribution_minor', 'monthly_contribution_ccy',
                'estimated_property_price_minor', 'estimated_property_price_ccy',
                'stamp_duty_estimate_minor', 'stamp_duty_estimate_ccy',
                'additional_costs_estimate_minor', 'additional_costs_estimate_ccy',
            ]);
        });

        Schema::table('goal_contributions', function (Blueprint $table) {
            $table->dropColumn([
                'amount_minor', 'amount_ccy',
                'goal_balance_after_minor', 'goal_balance_after_ccy',
            ]);
        });

        Schema::table('goal_savings_account', function (Blueprint $table) {
            $table->dropColumn(['allocated_amount_minor', 'allocated_amount_ccy']);
        });

        Schema::table('life_events', function (Blueprint $table) {
            $table->dropColumn(['amount_minor', 'amount_ccy']);
        });

        Schema::table('life_event_allocations', function (Blueprint $table) {
            $table->dropColumn([
                'suggested_amount_minor', 'suggested_amount_ccy',
                'amount_minor', 'amount_ccy',
            ]);
        });
    }
};
