<?php

declare(strict_types=1);

/**
 * Shadow columns for money internationalisation — Group 4: Investment
 *
 * Tables: investment_accounts (30 columns), holdings (4), investment_goals (1),
 *         investment_recommendations (1), rebalancing_actions (7), risk_metrics (5)
 * Total: 48 money columns -> 96 shadow columns (_minor bigInteger + _ccy char(3))
 *
 * Note: portfolio_optimizations has no money columns in the registry.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->bigInteger('current_value_minor')->nullable()->after('current_value');
            $table->char('current_value_ccy', 3)->nullable()->after('current_value_minor');
            $table->bigInteger('total_holdings_value_minor')->nullable()->after('total_holdings_value');
            $table->char('total_holdings_value_ccy', 3)->nullable()->after('total_holdings_value_minor');
            $table->bigInteger('contributions_ytd_minor')->nullable()->after('contributions_ytd');
            $table->char('contributions_ytd_ccy', 3)->nullable()->after('contributions_ytd_minor');
            $table->bigInteger('monthly_contribution_amount_minor')->nullable()->after('monthly_contribution_amount');
            $table->char('monthly_contribution_amount_ccy', 3)->nullable()->after('monthly_contribution_amount_minor');
            $table->bigInteger('planned_lump_sum_amount_minor')->nullable()->after('planned_lump_sum_amount');
            $table->char('planned_lump_sum_amount_ccy', 3)->nullable()->after('planned_lump_sum_amount_minor');
            $table->bigInteger('platform_fee_amount_minor')->nullable()->after('platform_fee_amount');
            $table->char('platform_fee_amount_ccy', 3)->nullable()->after('platform_fee_amount_minor');
            $table->bigInteger('isa_subscription_current_year_minor')->nullable()->after('isa_subscription_current_year');
            $table->char('isa_subscription_current_year_ccy', 3)->nullable()->after('isa_subscription_current_year_minor');
            $table->bigInteger('bond_withdrawal_taken_minor')->nullable()->after('bond_withdrawal_taken');
            $table->char('bond_withdrawal_taken_ccy', 3)->nullable()->after('bond_withdrawal_taken_minor');
            $table->bigInteger('badr_lifetime_used_minor')->nullable()->after('badr_lifetime_used');
            $table->char('badr_lifetime_used_ccy', 3)->nullable()->after('badr_lifetime_used_minor');
            $table->bigInteger('investment_amount_minor')->nullable()->after('investment_amount');
            $table->char('investment_amount_ccy', 3)->nullable()->after('investment_amount_minor');
            $table->bigInteger('pre_money_valuation_minor')->nullable()->after('pre_money_valuation');
            $table->char('pre_money_valuation_ccy', 3)->nullable()->after('pre_money_valuation_minor');
            $table->bigInteger('post_money_valuation_minor')->nullable()->after('post_money_valuation');
            $table->char('post_money_valuation_ccy', 3)->nullable()->after('post_money_valuation_minor');
            $table->bigInteger('price_per_share_minor')->nullable()->after('price_per_share');
            $table->char('price_per_share_ccy', 3)->nullable()->after('price_per_share_minor');
            $table->bigInteger('relief_amount_claimed_minor')->nullable()->after('relief_amount_claimed');
            $table->char('relief_amount_claimed_ccy', 3)->nullable()->after('relief_amount_claimed_minor');
            $table->bigInteger('latest_valuation_minor')->nullable()->after('latest_valuation');
            $table->char('latest_valuation_ccy', 3)->nullable()->after('latest_valuation_minor');
            $table->bigInteger('exit_gross_proceeds_minor')->nullable()->after('exit_gross_proceeds');
            $table->char('exit_gross_proceeds_ccy', 3)->nullable()->after('exit_gross_proceeds_minor');
            $table->bigInteger('exit_fees_minor')->nullable()->after('exit_fees');
            $table->char('exit_fees_ccy', 3)->nullable()->after('exit_fees_minor');
            $table->bigInteger('exit_net_proceeds_minor')->nullable()->after('exit_net_proceeds');
            $table->char('exit_net_proceeds_ccy', 3)->nullable()->after('exit_net_proceeds_minor');
            $table->bigInteger('capital_loss_amount_minor')->nullable()->after('capital_loss_amount');
            $table->char('capital_loss_amount_ccy', 3)->nullable()->after('capital_loss_amount_minor');
            $table->bigInteger('exercise_price_minor')->nullable()->after('exercise_price');
            $table->char('exercise_price_ccy', 3)->nullable()->after('exercise_price_minor');
            $table->bigInteger('market_value_at_grant_minor')->nullable()->after('market_value_at_grant');
            $table->char('market_value_at_grant_ccy', 3)->nullable()->after('market_value_at_grant_minor');
            $table->bigInteger('option_price_paid_minor')->nullable()->after('option_price_paid');
            $table->char('option_price_paid_ccy', 3)->nullable()->after('option_price_paid_minor');
            $table->bigInteger('current_share_price_minor')->nullable()->after('current_share_price');
            $table->char('current_share_price_ccy', 3)->nullable()->after('current_share_price_minor');
            $table->bigInteger('total_exercise_proceeds_minor')->nullable()->after('total_exercise_proceeds');
            $table->char('total_exercise_proceeds_ccy', 3)->nullable()->after('total_exercise_proceeds_minor');
            $table->bigInteger('total_exercise_cost_minor')->nullable()->after('total_exercise_cost');
            $table->char('total_exercise_cost_ccy', 3)->nullable()->after('total_exercise_cost_minor');
            $table->bigInteger('income_tax_at_vest_exercise_minor')->nullable()->after('income_tax_at_vest_exercise');
            $table->char('income_tax_at_vest_exercise_ccy', 3)->nullable()->after('income_tax_at_vest_exercise_minor');
            $table->bigInteger('ni_at_vest_exercise_minor')->nullable()->after('ni_at_vest_exercise');
            $table->char('ni_at_vest_exercise_ccy', 3)->nullable()->after('ni_at_vest_exercise_minor');
            $table->bigInteger('cost_basis_for_cgt_minor')->nullable()->after('cost_basis_for_cgt');
            $table->char('cost_basis_for_cgt_ccy', 3)->nullable()->after('cost_basis_for_cgt_minor');
            $table->bigInteger('saye_monthly_savings_minor')->nullable()->after('saye_monthly_savings');
            $table->char('saye_monthly_savings_ccy', 3)->nullable()->after('saye_monthly_savings_minor');
            $table->bigInteger('saye_current_savings_balance_minor')->nullable()->after('saye_current_savings_balance');
            $table->char('saye_current_savings_balance_ccy', 3)->nullable()->after('saye_current_savings_balance_minor');
            $table->bigInteger('saye_bonus_amount_minor')->nullable()->after('saye_bonus_amount');
            $table->char('saye_bonus_amount_ccy', 3)->nullable()->after('saye_bonus_amount_minor');
        });

        Schema::table('holdings', function (Blueprint $table) {
            $table->bigInteger('purchase_price_minor')->nullable()->after('purchase_price');
            $table->char('purchase_price_ccy', 3)->nullable()->after('purchase_price_minor');
            $table->bigInteger('current_price_minor')->nullable()->after('current_price');
            $table->char('current_price_ccy', 3)->nullable()->after('current_price_minor');
            $table->bigInteger('current_value_minor')->nullable()->after('current_value');
            $table->char('current_value_ccy', 3)->nullable()->after('current_value_minor');
            $table->bigInteger('cost_basis_minor')->nullable()->after('cost_basis');
            $table->char('cost_basis_ccy', 3)->nullable()->after('cost_basis_minor');
        });

        Schema::table('investment_goals', function (Blueprint $table) {
            $table->bigInteger('target_amount_minor')->nullable()->after('target_amount');
            $table->char('target_amount_ccy', 3)->nullable()->after('target_amount_minor');
        });

        Schema::table('investment_recommendations', function (Blueprint $table) {
            $table->bigInteger('potential_saving_minor')->nullable()->after('potential_saving');
            $table->char('potential_saving_ccy', 3)->nullable()->after('potential_saving_minor');
        });

        Schema::table('rebalancing_actions', function (Blueprint $table) {
            $table->bigInteger('trade_value_minor')->nullable()->after('trade_value');
            $table->char('trade_value_ccy', 3)->nullable()->after('trade_value_minor');
            $table->bigInteger('current_price_minor')->nullable()->after('current_price');
            $table->char('current_price_ccy', 3)->nullable()->after('current_price_minor');
            $table->bigInteger('target_value_minor')->nullable()->after('target_value');
            $table->char('target_value_ccy', 3)->nullable()->after('target_value_minor');
            $table->bigInteger('cgt_cost_basis_minor')->nullable()->after('cgt_cost_basis');
            $table->char('cgt_cost_basis_ccy', 3)->nullable()->after('cgt_cost_basis_minor');
            $table->bigInteger('cgt_gain_or_loss_minor')->nullable()->after('cgt_gain_or_loss');
            $table->char('cgt_gain_or_loss_ccy', 3)->nullable()->after('cgt_gain_or_loss_minor');
            $table->bigInteger('cgt_liability_minor')->nullable()->after('cgt_liability');
            $table->char('cgt_liability_ccy', 3)->nullable()->after('cgt_liability_minor');
            $table->bigInteger('executed_price_minor')->nullable()->after('executed_price');
            $table->char('executed_price_ccy', 3)->nullable()->after('executed_price_minor');
        });

        Schema::table('risk_metrics', function (Blueprint $table) {
            $table->bigInteger('portfolio_value_minor')->nullable()->after('portfolio_value');
            $table->char('portfolio_value_ccy', 3)->nullable()->after('portfolio_value_minor');
            $table->bigInteger('var_95_1month_minor')->nullable()->after('var_95_1month');
            $table->char('var_95_1month_ccy', 3)->nullable()->after('var_95_1month_minor');
            $table->bigInteger('cvar_95_1month_minor')->nullable()->after('cvar_95_1month');
            $table->char('cvar_95_1month_ccy', 3)->nullable()->after('cvar_95_1month_minor');
            $table->bigInteger('var_99_1month_minor')->nullable()->after('var_99_1month');
            $table->char('var_99_1month_ccy', 3)->nullable()->after('var_99_1month_minor');
            $table->bigInteger('cvar_99_1month_minor')->nullable()->after('cvar_99_1month');
            $table->char('cvar_99_1month_ccy', 3)->nullable()->after('cvar_99_1month_minor');
        });
    }

    public function down(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'current_value_minor', 'current_value_ccy',
                'total_holdings_value_minor', 'total_holdings_value_ccy',
                'contributions_ytd_minor', 'contributions_ytd_ccy',
                'monthly_contribution_amount_minor', 'monthly_contribution_amount_ccy',
                'planned_lump_sum_amount_minor', 'planned_lump_sum_amount_ccy',
                'platform_fee_amount_minor', 'platform_fee_amount_ccy',
                'isa_subscription_current_year_minor', 'isa_subscription_current_year_ccy',
                'bond_withdrawal_taken_minor', 'bond_withdrawal_taken_ccy',
                'badr_lifetime_used_minor', 'badr_lifetime_used_ccy',
                'investment_amount_minor', 'investment_amount_ccy',
                'pre_money_valuation_minor', 'pre_money_valuation_ccy',
                'post_money_valuation_minor', 'post_money_valuation_ccy',
                'price_per_share_minor', 'price_per_share_ccy',
                'relief_amount_claimed_minor', 'relief_amount_claimed_ccy',
                'latest_valuation_minor', 'latest_valuation_ccy',
                'exit_gross_proceeds_minor', 'exit_gross_proceeds_ccy',
                'exit_fees_minor', 'exit_fees_ccy',
                'exit_net_proceeds_minor', 'exit_net_proceeds_ccy',
                'capital_loss_amount_minor', 'capital_loss_amount_ccy',
                'exercise_price_minor', 'exercise_price_ccy',
                'market_value_at_grant_minor', 'market_value_at_grant_ccy',
                'option_price_paid_minor', 'option_price_paid_ccy',
                'current_share_price_minor', 'current_share_price_ccy',
                'total_exercise_proceeds_minor', 'total_exercise_proceeds_ccy',
                'total_exercise_cost_minor', 'total_exercise_cost_ccy',
                'income_tax_at_vest_exercise_minor', 'income_tax_at_vest_exercise_ccy',
                'ni_at_vest_exercise_minor', 'ni_at_vest_exercise_ccy',
                'cost_basis_for_cgt_minor', 'cost_basis_for_cgt_ccy',
                'saye_monthly_savings_minor', 'saye_monthly_savings_ccy',
                'saye_current_savings_balance_minor', 'saye_current_savings_balance_ccy',
                'saye_bonus_amount_minor', 'saye_bonus_amount_ccy',
            ]);
        });

        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_price_minor', 'purchase_price_ccy',
                'current_price_minor', 'current_price_ccy',
                'current_value_minor', 'current_value_ccy',
                'cost_basis_minor', 'cost_basis_ccy',
            ]);
        });

        Schema::table('investment_goals', function (Blueprint $table) {
            $table->dropColumn(['target_amount_minor', 'target_amount_ccy']);
        });

        Schema::table('investment_recommendations', function (Blueprint $table) {
            $table->dropColumn(['potential_saving_minor', 'potential_saving_ccy']);
        });

        Schema::table('rebalancing_actions', function (Blueprint $table) {
            $table->dropColumn([
                'trade_value_minor', 'trade_value_ccy',
                'current_price_minor', 'current_price_ccy',
                'target_value_minor', 'target_value_ccy',
                'cgt_cost_basis_minor', 'cgt_cost_basis_ccy',
                'cgt_gain_or_loss_minor', 'cgt_gain_or_loss_ccy',
                'cgt_liability_minor', 'cgt_liability_ccy',
                'executed_price_minor', 'executed_price_ccy',
            ]);
        });

        Schema::table('risk_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'portfolio_value_minor', 'portfolio_value_ccy',
                'var_95_1month_minor', 'var_95_1month_ccy',
                'cvar_95_1month_minor', 'cvar_95_1month_ccy',
                'var_99_1month_minor', 'var_99_1month_ccy',
                'cvar_99_1month_minor', 'cvar_99_1month_ccy',
            ]);
        });
    }
};
