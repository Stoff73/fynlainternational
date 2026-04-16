<?php

declare(strict_types=1);

/**
 * Shadow columns for money internationalisation — Group 5: Retirement
 *
 * Tables: dc_pensions (6 columns), db_pensions (3),
 *         state_pensions (2), retirement_profiles (5)
 * Total: 16 money columns -> 32 shadow columns (_minor bigInteger + _ccy char(3))
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->bigInteger('current_fund_value_minor')->nullable()->after('current_fund_value');
            $table->char('current_fund_value_ccy', 3)->nullable()->after('current_fund_value_minor');
            $table->bigInteger('annual_salary_minor')->nullable()->after('annual_salary');
            $table->char('annual_salary_ccy', 3)->nullable()->after('annual_salary_minor');
            $table->bigInteger('monthly_contribution_amount_minor')->nullable()->after('monthly_contribution_amount');
            $table->char('monthly_contribution_amount_ccy', 3)->nullable()->after('monthly_contribution_amount_minor');
            $table->bigInteger('lump_sum_contribution_minor')->nullable()->after('lump_sum_contribution');
            $table->char('lump_sum_contribution_ccy', 3)->nullable()->after('lump_sum_contribution_minor');
            $table->bigInteger('projected_value_at_retirement_minor')->nullable()->after('projected_value_at_retirement');
            $table->char('projected_value_at_retirement_ccy', 3)->nullable()->after('projected_value_at_retirement_minor');
            $table->bigInteger('platform_fee_amount_minor')->nullable()->after('platform_fee_amount');
            $table->char('platform_fee_amount_ccy', 3)->nullable()->after('platform_fee_amount_minor');
        });

        Schema::table('db_pensions', function (Blueprint $table) {
            $table->bigInteger('accrued_annual_pension_minor')->nullable()->after('accrued_annual_pension');
            $table->char('accrued_annual_pension_ccy', 3)->nullable()->after('accrued_annual_pension_minor');
            $table->bigInteger('pensionable_salary_minor')->nullable()->after('pensionable_salary');
            $table->char('pensionable_salary_ccy', 3)->nullable()->after('pensionable_salary_minor');
            $table->bigInteger('lump_sum_entitlement_minor')->nullable()->after('lump_sum_entitlement');
            $table->char('lump_sum_entitlement_ccy', 3)->nullable()->after('lump_sum_entitlement_minor');
        });

        Schema::table('state_pensions', function (Blueprint $table) {
            $table->bigInteger('state_pension_forecast_annual_minor')->nullable()->after('state_pension_forecast_annual');
            $table->char('state_pension_forecast_annual_ccy', 3)->nullable()->after('state_pension_forecast_annual_minor');
            $table->bigInteger('gap_fill_cost_minor')->nullable()->after('gap_fill_cost');
            $table->char('gap_fill_cost_ccy', 3)->nullable()->after('gap_fill_cost_minor');
        });

        Schema::table('retirement_profiles', function (Blueprint $table) {
            $table->bigInteger('current_annual_salary_minor')->nullable()->after('current_annual_salary');
            $table->char('current_annual_salary_ccy', 3)->nullable()->after('current_annual_salary_minor');
            $table->bigInteger('target_retirement_income_minor')->nullable()->after('target_retirement_income');
            $table->char('target_retirement_income_ccy', 3)->nullable()->after('target_retirement_income_minor');
            $table->bigInteger('essential_expenditure_minor')->nullable()->after('essential_expenditure');
            $table->char('essential_expenditure_ccy', 3)->nullable()->after('essential_expenditure_minor');
            $table->bigInteger('lifestyle_expenditure_minor')->nullable()->after('lifestyle_expenditure');
            $table->char('lifestyle_expenditure_ccy', 3)->nullable()->after('lifestyle_expenditure_minor');
            $table->bigInteger('care_cost_annual_minor')->nullable()->after('care_cost_annual');
            $table->char('care_cost_annual_ccy', 3)->nullable()->after('care_cost_annual_minor');
        });
    }

    public function down(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn([
                'current_fund_value_minor', 'current_fund_value_ccy',
                'annual_salary_minor', 'annual_salary_ccy',
                'monthly_contribution_amount_minor', 'monthly_contribution_amount_ccy',
                'lump_sum_contribution_minor', 'lump_sum_contribution_ccy',
                'projected_value_at_retirement_minor', 'projected_value_at_retirement_ccy',
                'platform_fee_amount_minor', 'platform_fee_amount_ccy',
            ]);
        });

        Schema::table('db_pensions', function (Blueprint $table) {
            $table->dropColumn([
                'accrued_annual_pension_minor', 'accrued_annual_pension_ccy',
                'pensionable_salary_minor', 'pensionable_salary_ccy',
                'lump_sum_entitlement_minor', 'lump_sum_entitlement_ccy',
            ]);
        });

        Schema::table('state_pensions', function (Blueprint $table) {
            $table->dropColumn([
                'state_pension_forecast_annual_minor', 'state_pension_forecast_annual_ccy',
                'gap_fill_cost_minor', 'gap_fill_cost_ccy',
            ]);
        });

        Schema::table('retirement_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'current_annual_salary_minor', 'current_annual_salary_ccy',
                'target_retirement_income_minor', 'target_retirement_income_ccy',
                'essential_expenditure_minor', 'essential_expenditure_ccy',
                'lifestyle_expenditure_minor', 'lifestyle_expenditure_ccy',
                'care_cost_annual_minor', 'care_cost_annual_ccy',
            ]);
        });
    }
};
