<?php

declare(strict_types=1);

/**
 * Shadow columns for money internationalisation — Group 3: Savings
 *
 * Tables: savings_accounts (4 columns), savings_goals (3),
 *         cash_accounts (2), isa_allowance_tracking (5)
 * Total: 14 money columns -> 28 shadow columns (_minor bigInteger + _ccy char(3))
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->bigInteger('current_balance_minor')->nullable()->after('current_balance');
            $table->char('current_balance_ccy', 3)->nullable()->after('current_balance_minor');
            $table->bigInteger('isa_subscription_amount_minor')->nullable()->after('isa_subscription_amount');
            $table->char('isa_subscription_amount_ccy', 3)->nullable()->after('isa_subscription_amount_minor');
            $table->bigInteger('regular_contribution_amount_minor')->nullable()->after('regular_contribution_amount');
            $table->char('regular_contribution_amount_ccy', 3)->nullable()->after('regular_contribution_amount_minor');
            $table->bigInteger('planned_lump_sum_amount_minor')->nullable()->after('planned_lump_sum_amount');
            $table->char('planned_lump_sum_amount_ccy', 3)->nullable()->after('planned_lump_sum_amount_minor');
        });

        Schema::table('savings_goals', function (Blueprint $table) {
            $table->bigInteger('target_amount_minor')->nullable()->after('target_amount');
            $table->char('target_amount_ccy', 3)->nullable()->after('target_amount_minor');
            $table->bigInteger('current_saved_minor')->nullable()->after('current_saved');
            $table->char('current_saved_ccy', 3)->nullable()->after('current_saved_minor');
            $table->bigInteger('auto_transfer_amount_minor')->nullable()->after('auto_transfer_amount');
            $table->char('auto_transfer_amount_ccy', 3)->nullable()->after('auto_transfer_amount_minor');
        });

        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->bigInteger('current_balance_minor')->nullable()->after('current_balance');
            $table->char('current_balance_ccy', 3)->nullable()->after('current_balance_minor');
            $table->bigInteger('isa_subscription_current_year_minor')->nullable()->after('isa_subscription_current_year');
            $table->char('isa_subscription_current_year_ccy', 3)->nullable()->after('isa_subscription_current_year_minor');
        });

        Schema::table('isa_allowance_tracking', function (Blueprint $table) {
            $table->bigInteger('cash_isa_used_minor')->nullable()->after('cash_isa_used');
            $table->char('cash_isa_used_ccy', 3)->nullable()->after('cash_isa_used_minor');
            $table->bigInteger('stocks_shares_isa_used_minor')->nullable()->after('stocks_shares_isa_used');
            $table->char('stocks_shares_isa_used_ccy', 3)->nullable()->after('stocks_shares_isa_used_minor');
            $table->bigInteger('lisa_used_minor')->nullable()->after('lisa_used');
            $table->char('lisa_used_ccy', 3)->nullable()->after('lisa_used_minor');
            $table->bigInteger('total_used_minor')->nullable()->after('total_used');
            $table->char('total_used_ccy', 3)->nullable()->after('total_used_minor');
            $table->bigInteger('total_allowance_minor')->nullable()->after('total_allowance');
            $table->char('total_allowance_ccy', 3)->nullable()->after('total_allowance_minor');
        });
    }

    public function down(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'current_balance_minor', 'current_balance_ccy',
                'isa_subscription_amount_minor', 'isa_subscription_amount_ccy',
                'regular_contribution_amount_minor', 'regular_contribution_amount_ccy',
                'planned_lump_sum_amount_minor', 'planned_lump_sum_amount_ccy',
            ]);
        });

        Schema::table('savings_goals', function (Blueprint $table) {
            $table->dropColumn([
                'target_amount_minor', 'target_amount_ccy',
                'current_saved_minor', 'current_saved_ccy',
                'auto_transfer_amount_minor', 'auto_transfer_amount_ccy',
            ]);
        });

        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'current_balance_minor', 'current_balance_ccy',
                'isa_subscription_current_year_minor', 'isa_subscription_current_year_ccy',
            ]);
        });

        Schema::table('isa_allowance_tracking', function (Blueprint $table) {
            $table->dropColumn([
                'cash_isa_used_minor', 'cash_isa_used_ccy',
                'stocks_shares_isa_used_minor', 'stocks_shares_isa_used_ccy',
                'lisa_used_minor', 'lisa_used_ccy',
                'total_used_minor', 'total_used_ccy',
                'total_allowance_minor', 'total_allowance_ccy',
            ]);
        });
    }
};
