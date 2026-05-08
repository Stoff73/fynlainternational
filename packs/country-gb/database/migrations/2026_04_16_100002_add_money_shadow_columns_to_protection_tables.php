<?php

declare(strict_types=1);

/**
 * Shadow columns for money internationalisation — Group 2: Protection
 *
 * Tables: life_insurance_policies (3 columns), critical_illness_policies (2),
 *         income_protection_policies (2), disability_policies (2),
 *         sickness_illness_policies (2), protection_profiles (5)
 * Total: 16 money columns -> 32 shadow columns (_minor bigInteger + _ccy char(3))
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('life_insurance_policies', function (Blueprint $table) {
            $table->bigInteger('sum_assured_minor')->nullable()->after('sum_assured');
            $table->char('sum_assured_ccy', 3)->nullable()->after('sum_assured_minor');
            $table->bigInteger('start_value_minor')->nullable()->after('start_value');
            $table->char('start_value_ccy', 3)->nullable()->after('start_value_minor');
            $table->bigInteger('premium_amount_minor')->nullable()->after('premium_amount');
            $table->char('premium_amount_ccy', 3)->nullable()->after('premium_amount_minor');
        });

        Schema::table('critical_illness_policies', function (Blueprint $table) {
            $table->bigInteger('sum_assured_minor')->nullable()->after('sum_assured');
            $table->char('sum_assured_ccy', 3)->nullable()->after('sum_assured_minor');
            $table->bigInteger('premium_amount_minor')->nullable()->after('premium_amount');
            $table->char('premium_amount_ccy', 3)->nullable()->after('premium_amount_minor');
        });

        Schema::table('income_protection_policies', function (Blueprint $table) {
            $table->bigInteger('benefit_amount_minor')->nullable()->after('benefit_amount');
            $table->char('benefit_amount_ccy', 3)->nullable()->after('benefit_amount_minor');
            $table->bigInteger('premium_amount_minor')->nullable()->after('premium_amount');
            $table->char('premium_amount_ccy', 3)->nullable()->after('premium_amount_minor');
        });

        Schema::table('disability_policies', function (Blueprint $table) {
            $table->bigInteger('benefit_amount_minor')->nullable()->after('benefit_amount');
            $table->char('benefit_amount_ccy', 3)->nullable()->after('benefit_amount_minor');
            $table->bigInteger('premium_amount_minor')->nullable()->after('premium_amount');
            $table->char('premium_amount_ccy', 3)->nullable()->after('premium_amount_minor');
        });

        Schema::table('sickness_illness_policies', function (Blueprint $table) {
            $table->bigInteger('benefit_amount_minor')->nullable()->after('benefit_amount');
            $table->char('benefit_amount_ccy', 3)->nullable()->after('benefit_amount_minor');
            $table->bigInteger('premium_amount_minor')->nullable()->after('premium_amount');
            $table->char('premium_amount_ccy', 3)->nullable()->after('premium_amount_minor');
        });

        Schema::table('protection_profiles', function (Blueprint $table) {
            $table->bigInteger('annual_income_minor')->nullable()->after('annual_income');
            $table->char('annual_income_ccy', 3)->nullable()->after('annual_income_minor');
            $table->bigInteger('monthly_expenditure_minor')->nullable()->after('monthly_expenditure');
            $table->char('monthly_expenditure_ccy', 3)->nullable()->after('monthly_expenditure_minor');
            $table->bigInteger('mortgage_balance_minor')->nullable()->after('mortgage_balance');
            $table->char('mortgage_balance_ccy', 3)->nullable()->after('mortgage_balance_minor');
            $table->bigInteger('other_debts_minor')->nullable()->after('other_debts');
            $table->char('other_debts_ccy', 3)->nullable()->after('other_debts_minor');
            $table->bigInteger('group_ci_amount_minor')->nullable()->after('group_ci_amount');
            $table->char('group_ci_amount_ccy', 3)->nullable()->after('group_ci_amount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('life_insurance_policies', function (Blueprint $table) {
            $table->dropColumn([
                'sum_assured_minor', 'sum_assured_ccy',
                'start_value_minor', 'start_value_ccy',
                'premium_amount_minor', 'premium_amount_ccy',
            ]);
        });

        Schema::table('critical_illness_policies', function (Blueprint $table) {
            $table->dropColumn([
                'sum_assured_minor', 'sum_assured_ccy',
                'premium_amount_minor', 'premium_amount_ccy',
            ]);
        });

        Schema::table('income_protection_policies', function (Blueprint $table) {
            $table->dropColumn([
                'benefit_amount_minor', 'benefit_amount_ccy',
                'premium_amount_minor', 'premium_amount_ccy',
            ]);
        });

        Schema::table('disability_policies', function (Blueprint $table) {
            $table->dropColumn([
                'benefit_amount_minor', 'benefit_amount_ccy',
                'premium_amount_minor', 'premium_amount_ccy',
            ]);
        });

        Schema::table('sickness_illness_policies', function (Blueprint $table) {
            $table->dropColumn([
                'benefit_amount_minor', 'benefit_amount_ccy',
                'premium_amount_minor', 'premium_amount_ccy',
            ]);
        });

        Schema::table('protection_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'annual_income_minor', 'annual_income_ccy',
                'monthly_expenditure_minor', 'monthly_expenditure_ccy',
                'mortgage_balance_minor', 'mortgage_balance_ccy',
                'other_debts_minor', 'other_debts_ccy',
                'group_ci_amount_minor', 'group_ci_amount_ccy',
            ]);
        });
    }
};
