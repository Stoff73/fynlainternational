<?php

declare(strict_types=1);

/**
 * Shadow columns for money internationalisation — Group 8: Property
 *
 * Tables: properties (19 columns), mortgages (4)
 * Total: 23 money columns -> 46 shadow columns (_minor bigInteger + _ccy char(3))
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->bigInteger('purchase_price_minor')->nullable()->after('purchase_price');
            $table->char('purchase_price_ccy', 3)->nullable()->after('purchase_price_minor');
            $table->bigInteger('current_value_minor')->nullable()->after('current_value');
            $table->char('current_value_ccy', 3)->nullable()->after('current_value_minor');
            $table->bigInteger('sdlt_paid_minor')->nullable()->after('sdlt_paid');
            $table->char('sdlt_paid_ccy', 3)->nullable()->after('sdlt_paid_minor');
            $table->bigInteger('monthly_rental_income_minor')->nullable()->after('monthly_rental_income');
            $table->char('monthly_rental_income_ccy', 3)->nullable()->after('monthly_rental_income_minor');
            $table->bigInteger('outstanding_mortgage_minor')->nullable()->after('outstanding_mortgage');
            $table->char('outstanding_mortgage_ccy', 3)->nullable()->after('outstanding_mortgage_minor');
            $table->bigInteger('total_mortgage_balance_minor')->nullable()->after('total_mortgage_balance');
            $table->char('total_mortgage_balance_ccy', 3)->nullable()->after('total_mortgage_balance_minor');
            $table->bigInteger('managing_agent_fee_minor')->nullable()->after('managing_agent_fee');
            $table->char('managing_agent_fee_ccy', 3)->nullable()->after('managing_agent_fee_minor');
            $table->bigInteger('monthly_council_tax_minor')->nullable()->after('monthly_council_tax');
            $table->char('monthly_council_tax_ccy', 3)->nullable()->after('monthly_council_tax_minor');
            $table->bigInteger('monthly_gas_minor')->nullable()->after('monthly_gas');
            $table->char('monthly_gas_ccy', 3)->nullable()->after('monthly_gas_minor');
            $table->bigInteger('monthly_electricity_minor')->nullable()->after('monthly_electricity');
            $table->char('monthly_electricity_ccy', 3)->nullable()->after('monthly_electricity_minor');
            $table->bigInteger('monthly_water_minor')->nullable()->after('monthly_water');
            $table->char('monthly_water_ccy', 3)->nullable()->after('monthly_water_minor');
            $table->bigInteger('monthly_building_insurance_minor')->nullable()->after('monthly_building_insurance');
            $table->char('monthly_building_insurance_ccy', 3)->nullable()->after('monthly_building_insurance_minor');
            $table->bigInteger('monthly_contents_insurance_minor')->nullable()->after('monthly_contents_insurance');
            $table->char('monthly_contents_insurance_ccy', 3)->nullable()->after('monthly_contents_insurance_minor');
            $table->bigInteger('monthly_service_charge_minor')->nullable()->after('monthly_service_charge');
            $table->char('monthly_service_charge_ccy', 3)->nullable()->after('monthly_service_charge_minor');
            $table->bigInteger('monthly_maintenance_reserve_minor')->nullable()->after('monthly_maintenance_reserve');
            $table->char('monthly_maintenance_reserve_ccy', 3)->nullable()->after('monthly_maintenance_reserve_minor');
            $table->bigInteger('other_monthly_costs_minor')->nullable()->after('other_monthly_costs');
            $table->char('other_monthly_costs_ccy', 3)->nullable()->after('other_monthly_costs_minor');
            $table->bigInteger('annual_service_charge_minor')->nullable()->after('annual_service_charge');
            $table->char('annual_service_charge_ccy', 3)->nullable()->after('annual_service_charge_minor');
            $table->bigInteger('annual_ground_rent_minor')->nullable()->after('annual_ground_rent');
            $table->char('annual_ground_rent_ccy', 3)->nullable()->after('annual_ground_rent_minor');
            $table->bigInteger('annual_insurance_minor')->nullable()->after('annual_insurance');
            $table->char('annual_insurance_ccy', 3)->nullable()->after('annual_insurance_minor');
            $table->bigInteger('annual_maintenance_reserve_minor')->nullable()->after('annual_maintenance_reserve');
            $table->char('annual_maintenance_reserve_ccy', 3)->nullable()->after('annual_maintenance_reserve_minor');
            $table->bigInteger('other_annual_costs_minor')->nullable()->after('other_annual_costs');
            $table->char('other_annual_costs_ccy', 3)->nullable()->after('other_annual_costs_minor');
        });

        Schema::table('mortgages', function (Blueprint $table) {
            $table->bigInteger('original_loan_amount_minor')->nullable()->after('original_loan_amount');
            $table->char('original_loan_amount_ccy', 3)->nullable()->after('original_loan_amount_minor');
            $table->bigInteger('outstanding_balance_minor')->nullable()->after('outstanding_balance');
            $table->char('outstanding_balance_ccy', 3)->nullable()->after('outstanding_balance_minor');
            $table->bigInteger('monthly_payment_minor')->nullable()->after('monthly_payment');
            $table->char('monthly_payment_ccy', 3)->nullable()->after('monthly_payment_minor');
            $table->bigInteger('monthly_interest_portion_minor')->nullable()->after('monthly_interest_portion');
            $table->char('monthly_interest_portion_ccy', 3)->nullable()->after('monthly_interest_portion_minor');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_price_minor', 'purchase_price_ccy',
                'current_value_minor', 'current_value_ccy',
                'sdlt_paid_minor', 'sdlt_paid_ccy',
                'monthly_rental_income_minor', 'monthly_rental_income_ccy',
                'outstanding_mortgage_minor', 'outstanding_mortgage_ccy',
                'total_mortgage_balance_minor', 'total_mortgage_balance_ccy',
                'managing_agent_fee_minor', 'managing_agent_fee_ccy',
                'monthly_council_tax_minor', 'monthly_council_tax_ccy',
                'monthly_gas_minor', 'monthly_gas_ccy',
                'monthly_electricity_minor', 'monthly_electricity_ccy',
                'monthly_water_minor', 'monthly_water_ccy',
                'monthly_building_insurance_minor', 'monthly_building_insurance_ccy',
                'monthly_contents_insurance_minor', 'monthly_contents_insurance_ccy',
                'monthly_service_charge_minor', 'monthly_service_charge_ccy',
                'monthly_maintenance_reserve_minor', 'monthly_maintenance_reserve_ccy',
                'other_monthly_costs_minor', 'other_monthly_costs_ccy',
                'annual_service_charge_minor', 'annual_service_charge_ccy',
                'annual_ground_rent_minor', 'annual_ground_rent_ccy',
                'annual_insurance_minor', 'annual_insurance_ccy',
                'annual_maintenance_reserve_minor', 'annual_maintenance_reserve_ccy',
                'other_annual_costs_minor', 'other_annual_costs_ccy',
            ]);
        });

        Schema::table('mortgages', function (Blueprint $table) {
            $table->dropColumn([
                'original_loan_amount_minor', 'original_loan_amount_ccy',
                'monthly_payment_minor', 'monthly_payment_ccy',
                'outstanding_balance_minor', 'outstanding_balance_ccy',
                'monthly_interest_portion_minor', 'monthly_interest_portion_ccy',
            ]);
        });
    }
};
