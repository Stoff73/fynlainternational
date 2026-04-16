<?php

declare(strict_types=1);

/**
 * Shadow columns for money internationalisation — Group 1: User & Family
 *
 * Tables: users (30 columns), family_members (1), expenditure_profiles (8), personal_accounts (1)
 * Total: 40 money columns -> 80 shadow columns (_minor bigInteger + _ccy char(3))
 *
 * Note: The users table stores some money columns as DOUBLE rather than DECIMAL.
 * The shadow columns are identical regardless (bigInteger _minor + char(3) _ccy).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('annual_employment_income_minor')->nullable()->after('annual_employment_income');
            $table->char('annual_employment_income_ccy', 3)->nullable()->after('annual_employment_income_minor');
            $table->bigInteger('annual_self_employment_income_minor')->nullable()->after('annual_self_employment_income');
            $table->char('annual_self_employment_income_ccy', 3)->nullable()->after('annual_self_employment_income_minor');
            $table->bigInteger('annual_rental_income_minor')->nullable()->after('annual_rental_income');
            $table->char('annual_rental_income_ccy', 3)->nullable()->after('annual_rental_income_minor');
            $table->bigInteger('annual_dividend_income_minor')->nullable()->after('annual_dividend_income');
            $table->char('annual_dividend_income_ccy', 3)->nullable()->after('annual_dividend_income_minor');
            $table->bigInteger('annual_interest_income_minor')->nullable()->after('annual_interest_income');
            $table->char('annual_interest_income_ccy', 3)->nullable()->after('annual_interest_income_minor');
            $table->bigInteger('annual_other_income_minor')->nullable()->after('annual_other_income');
            $table->char('annual_other_income_ccy', 3)->nullable()->after('annual_other_income_minor');
            $table->bigInteger('annual_trust_income_minor')->nullable()->after('annual_trust_income');
            $table->char('annual_trust_income_ccy', 3)->nullable()->after('annual_trust_income_minor');
            $table->bigInteger('monthly_expenditure_minor')->nullable()->after('monthly_expenditure');
            $table->char('monthly_expenditure_ccy', 3)->nullable()->after('monthly_expenditure_minor');
            $table->bigInteger('annual_expenditure_minor')->nullable()->after('annual_expenditure');
            $table->char('annual_expenditure_ccy', 3)->nullable()->after('annual_expenditure_minor');
            $table->bigInteger('food_groceries_minor')->nullable()->after('food_groceries');
            $table->char('food_groceries_ccy', 3)->nullable()->after('food_groceries_minor');
            $table->bigInteger('transport_fuel_minor')->nullable()->after('transport_fuel');
            $table->char('transport_fuel_ccy', 3)->nullable()->after('transport_fuel_minor');
            $table->bigInteger('healthcare_medical_minor')->nullable()->after('healthcare_medical');
            $table->char('healthcare_medical_ccy', 3)->nullable()->after('healthcare_medical_minor');
            $table->bigInteger('insurance_minor')->nullable()->after('insurance');
            $table->char('insurance_ccy', 3)->nullable()->after('insurance_minor');
            $table->bigInteger('mobile_phones_minor')->nullable()->after('mobile_phones');
            $table->char('mobile_phones_ccy', 3)->nullable()->after('mobile_phones_minor');
            $table->bigInteger('internet_tv_minor')->nullable()->after('internet_tv');
            $table->char('internet_tv_ccy', 3)->nullable()->after('internet_tv_minor');
            $table->bigInteger('subscriptions_minor')->nullable()->after('subscriptions');
            $table->char('subscriptions_ccy', 3)->nullable()->after('subscriptions_minor');
            $table->bigInteger('clothing_personal_care_minor')->nullable()->after('clothing_personal_care');
            $table->char('clothing_personal_care_ccy', 3)->nullable()->after('clothing_personal_care_minor');
            $table->bigInteger('entertainment_dining_minor')->nullable()->after('entertainment_dining');
            $table->char('entertainment_dining_ccy', 3)->nullable()->after('entertainment_dining_minor');
            $table->bigInteger('holidays_travel_minor')->nullable()->after('holidays_travel');
            $table->char('holidays_travel_ccy', 3)->nullable()->after('holidays_travel_minor');
            $table->bigInteger('pets_minor')->nullable()->after('pets');
            $table->char('pets_ccy', 3)->nullable()->after('pets_minor');
            $table->bigInteger('childcare_minor')->nullable()->after('childcare');
            $table->char('childcare_ccy', 3)->nullable()->after('childcare_minor');
            $table->bigInteger('school_fees_minor')->nullable()->after('school_fees');
            $table->char('school_fees_ccy', 3)->nullable()->after('school_fees_minor');
            $table->bigInteger('school_lunches_minor')->nullable()->after('school_lunches');
            $table->char('school_lunches_ccy', 3)->nullable()->after('school_lunches_minor');
            $table->bigInteger('school_extras_minor')->nullable()->after('school_extras');
            $table->char('school_extras_ccy', 3)->nullable()->after('school_extras_minor');
            $table->bigInteger('university_fees_minor')->nullable()->after('university_fees');
            $table->char('university_fees_ccy', 3)->nullable()->after('university_fees_minor');
            $table->bigInteger('children_activities_minor')->nullable()->after('children_activities');
            $table->char('children_activities_ccy', 3)->nullable()->after('children_activities_minor');
            $table->bigInteger('gifts_charity_minor')->nullable()->after('gifts_charity');
            $table->char('gifts_charity_ccy', 3)->nullable()->after('gifts_charity_minor');
            $table->bigInteger('regular_savings_minor')->nullable()->after('regular_savings');
            $table->char('regular_savings_ccy', 3)->nullable()->after('regular_savings_minor');
            $table->bigInteger('other_expenditure_minor')->nullable()->after('other_expenditure');
            $table->char('other_expenditure_ccy', 3)->nullable()->after('other_expenditure_minor');
            $table->bigInteger('rent_minor')->nullable()->after('rent');
            $table->char('rent_ccy', 3)->nullable()->after('rent_minor');
            $table->bigInteger('utilities_minor')->nullable()->after('utilities');
            $table->char('utilities_ccy', 3)->nullable()->after('utilities_minor');
            $table->bigInteger('annual_charitable_donations_minor')->nullable()->after('annual_charitable_donations');
            $table->char('annual_charitable_donations_ccy', 3)->nullable()->after('annual_charitable_donations_minor');
        });

        Schema::table('family_members', function (Blueprint $table) {
            $table->bigInteger('annual_income_minor')->nullable()->after('annual_income');
            $table->char('annual_income_ccy', 3)->nullable()->after('annual_income_minor');
        });

        Schema::table('expenditure_profiles', function (Blueprint $table) {
            $table->bigInteger('monthly_housing_minor')->nullable()->after('monthly_housing');
            $table->char('monthly_housing_ccy', 3)->nullable()->after('monthly_housing_minor');
            $table->bigInteger('monthly_utilities_minor')->nullable()->after('monthly_utilities');
            $table->char('monthly_utilities_ccy', 3)->nullable()->after('monthly_utilities_minor');
            $table->bigInteger('monthly_food_minor')->nullable()->after('monthly_food');
            $table->char('monthly_food_ccy', 3)->nullable()->after('monthly_food_minor');
            $table->bigInteger('monthly_transport_minor')->nullable()->after('monthly_transport');
            $table->char('monthly_transport_ccy', 3)->nullable()->after('monthly_transport_minor');
            $table->bigInteger('monthly_insurance_minor')->nullable()->after('monthly_insurance');
            $table->char('monthly_insurance_ccy', 3)->nullable()->after('monthly_insurance_minor');
            $table->bigInteger('monthly_loans_minor')->nullable()->after('monthly_loans');
            $table->char('monthly_loans_ccy', 3)->nullable()->after('monthly_loans_minor');
            $table->bigInteger('monthly_discretionary_minor')->nullable()->after('monthly_discretionary');
            $table->char('monthly_discretionary_ccy', 3)->nullable()->after('monthly_discretionary_minor');
            $table->bigInteger('total_monthly_expenditure_minor')->nullable()->after('total_monthly_expenditure');
            $table->char('total_monthly_expenditure_ccy', 3)->nullable()->after('total_monthly_expenditure_minor');
        });

        Schema::table('personal_accounts', function (Blueprint $table) {
            $table->bigInteger('amount_minor')->nullable()->after('amount');
            $table->char('amount_ccy', 3)->nullable()->after('amount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'annual_employment_income_minor', 'annual_employment_income_ccy',
                'annual_self_employment_income_minor', 'annual_self_employment_income_ccy',
                'annual_rental_income_minor', 'annual_rental_income_ccy',
                'annual_dividend_income_minor', 'annual_dividend_income_ccy',
                'annual_interest_income_minor', 'annual_interest_income_ccy',
                'annual_other_income_minor', 'annual_other_income_ccy',
                'annual_trust_income_minor', 'annual_trust_income_ccy',
                'monthly_expenditure_minor', 'monthly_expenditure_ccy',
                'annual_expenditure_minor', 'annual_expenditure_ccy',
                'food_groceries_minor', 'food_groceries_ccy',
                'transport_fuel_minor', 'transport_fuel_ccy',
                'healthcare_medical_minor', 'healthcare_medical_ccy',
                'insurance_minor', 'insurance_ccy',
                'mobile_phones_minor', 'mobile_phones_ccy',
                'internet_tv_minor', 'internet_tv_ccy',
                'subscriptions_minor', 'subscriptions_ccy',
                'clothing_personal_care_minor', 'clothing_personal_care_ccy',
                'entertainment_dining_minor', 'entertainment_dining_ccy',
                'holidays_travel_minor', 'holidays_travel_ccy',
                'pets_minor', 'pets_ccy',
                'childcare_minor', 'childcare_ccy',
                'school_fees_minor', 'school_fees_ccy',
                'school_lunches_minor', 'school_lunches_ccy',
                'school_extras_minor', 'school_extras_ccy',
                'university_fees_minor', 'university_fees_ccy',
                'children_activities_minor', 'children_activities_ccy',
                'gifts_charity_minor', 'gifts_charity_ccy',
                'regular_savings_minor', 'regular_savings_ccy',
                'other_expenditure_minor', 'other_expenditure_ccy',
                'rent_minor', 'rent_ccy',
                'utilities_minor', 'utilities_ccy',
                'annual_charitable_donations_minor', 'annual_charitable_donations_ccy',
            ]);
        });

        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn(['annual_income_minor', 'annual_income_ccy']);
        });

        Schema::table('expenditure_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_housing_minor', 'monthly_housing_ccy',
                'monthly_utilities_minor', 'monthly_utilities_ccy',
                'monthly_food_minor', 'monthly_food_ccy',
                'monthly_transport_minor', 'monthly_transport_ccy',
                'monthly_insurance_minor', 'monthly_insurance_ccy',
                'monthly_loans_minor', 'monthly_loans_ccy',
                'monthly_discretionary_minor', 'monthly_discretionary_ccy',
                'total_monthly_expenditure_minor', 'total_monthly_expenditure_ccy',
            ]);
        });

        Schema::table('personal_accounts', function (Blueprint $table) {
            $table->dropColumn(['amount_minor', 'amount_ccy']);
        });
    }
};
