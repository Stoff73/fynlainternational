<?php

declare(strict_types=1);

/**
 * Shadow columns for money internationalisation — Group 6: Estate
 *
 * Tables: iht_calculations (22 columns), iht_profiles (3), trusts (9),
 *         gifts (1), bequests (1), assets (1), chattels (2),
 *         liabilities (2), net_worth_statements (3)
 * Total: 44 money columns -> 88 shadow columns (_minor bigInteger + _ccy char(3))
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iht_calculations', function (Blueprint $table) {
            $table->bigInteger('user_gross_assets_minor')->nullable()->after('user_gross_assets');
            $table->char('user_gross_assets_ccy', 3)->nullable()->after('user_gross_assets_minor');
            $table->bigInteger('spouse_gross_assets_minor')->nullable()->after('spouse_gross_assets');
            $table->char('spouse_gross_assets_ccy', 3)->nullable()->after('spouse_gross_assets_minor');
            $table->bigInteger('total_gross_assets_minor')->nullable()->after('total_gross_assets');
            $table->char('total_gross_assets_ccy', 3)->nullable()->after('total_gross_assets_minor');
            $table->bigInteger('user_total_liabilities_minor')->nullable()->after('user_total_liabilities');
            $table->char('user_total_liabilities_ccy', 3)->nullable()->after('user_total_liabilities_minor');
            $table->bigInteger('spouse_total_liabilities_minor')->nullable()->after('spouse_total_liabilities');
            $table->char('spouse_total_liabilities_ccy', 3)->nullable()->after('spouse_total_liabilities_minor');
            $table->bigInteger('total_liabilities_minor')->nullable()->after('total_liabilities');
            $table->char('total_liabilities_ccy', 3)->nullable()->after('total_liabilities_minor');
            $table->bigInteger('user_net_estate_minor')->nullable()->after('user_net_estate');
            $table->char('user_net_estate_ccy', 3)->nullable()->after('user_net_estate_minor');
            $table->bigInteger('spouse_net_estate_minor')->nullable()->after('spouse_net_estate');
            $table->char('spouse_net_estate_ccy', 3)->nullable()->after('spouse_net_estate_minor');
            $table->bigInteger('total_net_estate_minor')->nullable()->after('total_net_estate');
            $table->char('total_net_estate_ccy', 3)->nullable()->after('total_net_estate_minor');
            $table->bigInteger('nrb_available_minor')->nullable()->after('nrb_available');
            $table->char('nrb_available_ccy', 3)->nullable()->after('nrb_available_minor');
            $table->bigInteger('rnrb_available_minor')->nullable()->after('rnrb_available');
            $table->char('rnrb_available_ccy', 3)->nullable()->after('rnrb_available_minor');
            $table->bigInteger('total_allowances_minor')->nullable()->after('total_allowances');
            $table->char('total_allowances_ccy', 3)->nullable()->after('total_allowances_minor');
            $table->bigInteger('taxable_estate_minor')->nullable()->after('taxable_estate');
            $table->char('taxable_estate_ccy', 3)->nullable()->after('taxable_estate_minor');
            $table->bigInteger('iht_liability_minor')->nullable()->after('iht_liability');
            $table->char('iht_liability_ccy', 3)->nullable()->after('iht_liability_minor');
            $table->bigInteger('projected_gross_assets_minor')->nullable()->after('projected_gross_assets');
            $table->char('projected_gross_assets_ccy', 3)->nullable()->after('projected_gross_assets_minor');
            $table->bigInteger('projected_liabilities_minor')->nullable()->after('projected_liabilities');
            $table->char('projected_liabilities_ccy', 3)->nullable()->after('projected_liabilities_minor');
            $table->bigInteger('projected_net_estate_minor')->nullable()->after('projected_net_estate');
            $table->char('projected_net_estate_ccy', 3)->nullable()->after('projected_net_estate_minor');
            $table->bigInteger('projected_taxable_estate_minor')->nullable()->after('projected_taxable_estate');
            $table->char('projected_taxable_estate_ccy', 3)->nullable()->after('projected_taxable_estate_minor');
            $table->bigInteger('projected_iht_liability_minor')->nullable()->after('projected_iht_liability');
            $table->char('projected_iht_liability_ccy', 3)->nullable()->after('projected_iht_liability_minor');
            $table->bigInteger('projected_cash_minor')->nullable()->after('projected_cash');
            $table->char('projected_cash_ccy', 3)->nullable()->after('projected_cash_minor');
            $table->bigInteger('projected_investments_minor')->nullable()->after('projected_investments');
            $table->char('projected_investments_ccy', 3)->nullable()->after('projected_investments_minor');
            $table->bigInteger('projected_properties_minor')->nullable()->after('projected_properties');
            $table->char('projected_properties_ccy', 3)->nullable()->after('projected_properties_minor');
        });

        Schema::table('iht_profiles', function (Blueprint $table) {
            $table->bigInteger('home_value_minor')->nullable()->after('home_value');
            $table->char('home_value_ccy', 3)->nullable()->after('home_value_minor');
            $table->bigInteger('nrb_transferred_from_spouse_minor')->nullable()->after('nrb_transferred_from_spouse');
            $table->char('nrb_transferred_from_spouse_ccy', 3)->nullable()->after('nrb_transferred_from_spouse_minor');
            $table->bigInteger('rnrb_transferred_from_spouse_minor')->nullable()->after('rnrb_transferred_from_spouse');
            $table->char('rnrb_transferred_from_spouse_ccy', 3)->nullable()->after('rnrb_transferred_from_spouse_minor');
        });

        Schema::table('trusts', function (Blueprint $table) {
            $table->bigInteger('initial_value_minor')->nullable()->after('initial_value');
            $table->char('initial_value_ccy', 3)->nullable()->after('initial_value_minor');
            $table->bigInteger('current_value_minor')->nullable()->after('current_value');
            $table->char('current_value_ccy', 3)->nullable()->after('current_value_minor');
            $table->bigInteger('discount_amount_minor')->nullable()->after('discount_amount');
            $table->char('discount_amount_ccy', 3)->nullable()->after('discount_amount_minor');
            $table->bigInteger('retained_income_annual_minor')->nullable()->after('retained_income_annual');
            $table->char('retained_income_annual_ccy', 3)->nullable()->after('retained_income_annual_minor');
            $table->bigInteger('loan_amount_minor')->nullable()->after('loan_amount');
            $table->char('loan_amount_ccy', 3)->nullable()->after('loan_amount_minor');
            $table->bigInteger('sum_assured_minor')->nullable()->after('sum_assured');
            $table->char('sum_assured_ccy', 3)->nullable()->after('sum_assured_minor');
            $table->bigInteger('annual_premium_minor')->nullable()->after('annual_premium');
            $table->char('annual_premium_ccy', 3)->nullable()->after('annual_premium_minor');
            $table->bigInteger('last_periodic_charge_amount_minor')->nullable()->after('last_periodic_charge_amount');
            $table->char('last_periodic_charge_amount_ccy', 3)->nullable()->after('last_periodic_charge_amount_minor');
            $table->bigInteger('total_asset_value_minor')->nullable()->after('total_asset_value');
            $table->char('total_asset_value_ccy', 3)->nullable()->after('total_asset_value_minor');
        });

        Schema::table('gifts', function (Blueprint $table) {
            $table->bigInteger('gift_value_minor')->nullable()->after('gift_value');
            $table->char('gift_value_ccy', 3)->nullable()->after('gift_value_minor');
        });

        Schema::table('bequests', function (Blueprint $table) {
            $table->bigInteger('specific_amount_minor')->nullable()->after('specific_amount');
            $table->char('specific_amount_ccy', 3)->nullable()->after('specific_amount_minor');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->bigInteger('current_value_minor')->nullable()->after('current_value');
            $table->char('current_value_ccy', 3)->nullable()->after('current_value_minor');
        });

        Schema::table('chattels', function (Blueprint $table) {
            $table->bigInteger('purchase_price_minor')->nullable()->after('purchase_price');
            $table->char('purchase_price_ccy', 3)->nullable()->after('purchase_price_minor');
            $table->bigInteger('current_value_minor')->nullable()->after('current_value');
            $table->char('current_value_ccy', 3)->nullable()->after('current_value_minor');
        });

        Schema::table('liabilities', function (Blueprint $table) {
            $table->bigInteger('current_balance_minor')->nullable()->after('current_balance');
            $table->char('current_balance_ccy', 3)->nullable()->after('current_balance_minor');
            $table->bigInteger('monthly_payment_minor')->nullable()->after('monthly_payment');
            $table->char('monthly_payment_ccy', 3)->nullable()->after('monthly_payment_minor');
        });

        Schema::table('net_worth_statements', function (Blueprint $table) {
            $table->bigInteger('total_assets_minor')->nullable()->after('total_assets');
            $table->char('total_assets_ccy', 3)->nullable()->after('total_assets_minor');
            $table->bigInteger('total_liabilities_minor')->nullable()->after('total_liabilities');
            $table->char('total_liabilities_ccy', 3)->nullable()->after('total_liabilities_minor');
            $table->bigInteger('net_worth_minor')->nullable()->after('net_worth');
            $table->char('net_worth_ccy', 3)->nullable()->after('net_worth_minor');
        });
    }

    public function down(): void
    {
        Schema::table('iht_calculations', function (Blueprint $table) {
            $table->dropColumn([
                'user_gross_assets_minor', 'user_gross_assets_ccy',
                'spouse_gross_assets_minor', 'spouse_gross_assets_ccy',
                'total_gross_assets_minor', 'total_gross_assets_ccy',
                'user_total_liabilities_minor', 'user_total_liabilities_ccy',
                'spouse_total_liabilities_minor', 'spouse_total_liabilities_ccy',
                'total_liabilities_minor', 'total_liabilities_ccy',
                'user_net_estate_minor', 'user_net_estate_ccy',
                'spouse_net_estate_minor', 'spouse_net_estate_ccy',
                'total_net_estate_minor', 'total_net_estate_ccy',
                'nrb_available_minor', 'nrb_available_ccy',
                'rnrb_available_minor', 'rnrb_available_ccy',
                'total_allowances_minor', 'total_allowances_ccy',
                'taxable_estate_minor', 'taxable_estate_ccy',
                'iht_liability_minor', 'iht_liability_ccy',
                'projected_gross_assets_minor', 'projected_gross_assets_ccy',
                'projected_liabilities_minor', 'projected_liabilities_ccy',
                'projected_net_estate_minor', 'projected_net_estate_ccy',
                'projected_taxable_estate_minor', 'projected_taxable_estate_ccy',
                'projected_iht_liability_minor', 'projected_iht_liability_ccy',
                'projected_cash_minor', 'projected_cash_ccy',
                'projected_investments_minor', 'projected_investments_ccy',
                'projected_properties_minor', 'projected_properties_ccy',
            ]);
        });

        Schema::table('iht_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'home_value_minor', 'home_value_ccy',
                'nrb_transferred_from_spouse_minor', 'nrb_transferred_from_spouse_ccy',
                'rnrb_transferred_from_spouse_minor', 'rnrb_transferred_from_spouse_ccy',
            ]);
        });

        Schema::table('trusts', function (Blueprint $table) {
            $table->dropColumn([
                'initial_value_minor', 'initial_value_ccy',
                'current_value_minor', 'current_value_ccy',
                'discount_amount_minor', 'discount_amount_ccy',
                'retained_income_annual_minor', 'retained_income_annual_ccy',
                'loan_amount_minor', 'loan_amount_ccy',
                'sum_assured_minor', 'sum_assured_ccy',
                'annual_premium_minor', 'annual_premium_ccy',
                'last_periodic_charge_amount_minor', 'last_periodic_charge_amount_ccy',
                'total_asset_value_minor', 'total_asset_value_ccy',
            ]);
        });

        Schema::table('gifts', function (Blueprint $table) {
            $table->dropColumn(['gift_value_minor', 'gift_value_ccy']);
        });

        Schema::table('bequests', function (Blueprint $table) {
            $table->dropColumn(['specific_amount_minor', 'specific_amount_ccy']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['current_value_minor', 'current_value_ccy']);
        });

        Schema::table('chattels', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_price_minor', 'purchase_price_ccy',
                'current_value_minor', 'current_value_ccy',
            ]);
        });

        Schema::table('liabilities', function (Blueprint $table) {
            $table->dropColumn([
                'current_balance_minor', 'current_balance_ccy',
                'monthly_payment_minor', 'monthly_payment_ccy',
            ]);
        });

        Schema::table('net_worth_statements', function (Blueprint $table) {
            $table->dropColumn([
                'total_assets_minor', 'total_assets_ccy',
                'total_liabilities_minor', 'total_liabilities_ccy',
                'net_worth_minor', 'net_worth_ccy',
            ]);
        });
    }
};
