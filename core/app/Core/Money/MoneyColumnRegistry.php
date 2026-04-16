<?php

declare(strict_types=1);

namespace Fynla\Core\Money;

/**
 * Single source of truth for all money columns in the database.
 *
 * Used by:
 * - money:generate-migration to create shadow columns
 * - money:backfill to populate shadow columns
 * - Architecture tests to verify compliance
 * - HasMoney trait for default currency resolution
 *
 * Each entry maps: table_name => [column_name => default_currency_code]
 *
 * Columns NOT included (non-money decimals):
 * - Percentages (ownership_percentage, interest_rate, etc.)
 * - Statistical values (sharpe_ratio, beta, r_squared, etc.)
 * - Quantities (shares, units)
 * - Years/durations
 * - Probabilities
 */
final class MoneyColumnRegistry
{
    /**
     * Get all tables and their money columns.
     *
     * @return array<string, array<string, string>> table => [column => currency]
     */
    public static function all(): array
    {
        return [
            'assets' => [
                'current_value' => 'GBP',
            ],
            'bequests' => [
                'specific_amount' => 'GBP',
            ],
            'business_interests' => [
                'acquisition_cost' => 'GBP',
                'current_valuation' => 'GBP',
                'annual_revenue' => 'GBP',
                'annual_profit' => 'GBP',
                'annual_dividend_income' => 'GBP',
            ],
            'cash_accounts' => [
                'current_balance' => 'GBP',
                'isa_subscription_current_year' => 'GBP',
            ],
            'chattels' => [
                'purchase_price' => 'GBP',
                'current_value' => 'GBP',
            ],
            'critical_illness_policies' => [
                'sum_assured' => 'GBP',
                'premium_amount' => 'GBP',
            ],
            'db_pensions' => [
                'accrued_annual_pension' => 'GBP',
                'pensionable_salary' => 'GBP',
                'lump_sum_entitlement' => 'GBP',
            ],
            'dc_pensions' => [
                'current_fund_value' => 'GBP',
                'annual_salary' => 'GBP',
                'monthly_contribution_amount' => 'GBP',
                'lump_sum_contribution' => 'GBP',
                'projected_value_at_retirement' => 'GBP',
                'platform_fee_amount' => 'GBP',
            ],
            'disability_policies' => [
                'benefit_amount' => 'GBP',
                'premium_amount' => 'GBP',
            ],
            'expenditure_profiles' => [
                'monthly_housing' => 'GBP',
                'monthly_utilities' => 'GBP',
                'monthly_food' => 'GBP',
                'monthly_transport' => 'GBP',
                'monthly_insurance' => 'GBP',
                'monthly_loans' => 'GBP',
                'monthly_discretionary' => 'GBP',
                'total_monthly_expenditure' => 'GBP',
            ],
            'family_members' => [
                'annual_income' => 'GBP',
            ],
            'gifts' => [
                'gift_value' => 'GBP',
            ],
            'goal_contributions' => [
                'amount' => 'GBP',
                'goal_balance_after' => 'GBP',
            ],
            'goals' => [
                'target_amount' => 'GBP',
                'current_amount' => 'GBP',
                'monthly_contribution' => 'GBP',
                'estimated_property_price' => 'GBP',
                'stamp_duty_estimate' => 'GBP',
                'additional_costs_estimate' => 'GBP',
            ],
            'goal_savings_account' => [
                'allocated_amount' => 'GBP',
            ],
            'holdings' => [
                'purchase_price' => 'GBP',
                'current_price' => 'GBP',
                'current_value' => 'GBP',
                'cost_basis' => 'GBP',
            ],
            'iht_calculations' => [
                'user_gross_assets' => 'GBP',
                'spouse_gross_assets' => 'GBP',
                'total_gross_assets' => 'GBP',
                'user_total_liabilities' => 'GBP',
                'spouse_total_liabilities' => 'GBP',
                'total_liabilities' => 'GBP',
                'user_net_estate' => 'GBP',
                'spouse_net_estate' => 'GBP',
                'total_net_estate' => 'GBP',
                'nrb_available' => 'GBP',
                'rnrb_available' => 'GBP',
                'total_allowances' => 'GBP',
                'taxable_estate' => 'GBP',
                'iht_liability' => 'GBP',
                'projected_gross_assets' => 'GBP',
                'projected_liabilities' => 'GBP',
                'projected_net_estate' => 'GBP',
                'projected_taxable_estate' => 'GBP',
                'projected_iht_liability' => 'GBP',
                'projected_cash' => 'GBP',
                'projected_investments' => 'GBP',
                'projected_properties' => 'GBP',
            ],
            'iht_profiles' => [
                'home_value' => 'GBP',
                'nrb_transferred_from_spouse' => 'GBP',
                'rnrb_transferred_from_spouse' => 'GBP',
            ],
            'income_protection_policies' => [
                'benefit_amount' => 'GBP',
                'premium_amount' => 'GBP',
            ],
            'investment_accounts' => [
                'current_value' => 'GBP',
                'total_holdings_value' => 'GBP',
                'contributions_ytd' => 'GBP',
                'monthly_contribution_amount' => 'GBP',
                'planned_lump_sum_amount' => 'GBP',
                'platform_fee_amount' => 'GBP',
                'isa_subscription_current_year' => 'GBP',
                'bond_withdrawal_taken' => 'GBP',
                'badr_lifetime_used' => 'GBP',
                'investment_amount' => 'GBP',
                'pre_money_valuation' => 'GBP',
                'post_money_valuation' => 'GBP',
                'price_per_share' => 'GBP',
                'relief_amount_claimed' => 'GBP',
                'latest_valuation' => 'GBP',
                'exit_gross_proceeds' => 'GBP',
                'exit_fees' => 'GBP',
                'exit_net_proceeds' => 'GBP',
                'capital_loss_amount' => 'GBP',
                'exercise_price' => 'GBP',
                'market_value_at_grant' => 'GBP',
                'option_price_paid' => 'GBP',
                'current_share_price' => 'GBP',
                'total_exercise_proceeds' => 'GBP',
                'total_exercise_cost' => 'GBP',
                'income_tax_at_vest_exercise' => 'GBP',
                'ni_at_vest_exercise' => 'GBP',
                'cost_basis_for_cgt' => 'GBP',
                'saye_monthly_savings' => 'GBP',
                'saye_current_savings_balance' => 'GBP',
                'saye_bonus_amount' => 'GBP',
            ],
            'investment_goals' => [
                'target_amount' => 'GBP',
            ],
            'investment_recommendations' => [
                'potential_saving' => 'GBP',
            ],
            'isa_allowance_tracking' => [
                'cash_isa_used' => 'GBP',
                'stocks_shares_isa_used' => 'GBP',
                'lisa_used' => 'GBP',
                'total_used' => 'GBP',
                'total_allowance' => 'GBP',
            ],
            'liabilities' => [
                'current_balance' => 'GBP',
                'monthly_payment' => 'GBP',
            ],
            'life_events' => [
                'amount' => 'GBP',
            ],
            'life_event_allocations' => [
                'suggested_amount' => 'GBP',
                'amount' => 'GBP',
            ],
            'life_insurance_policies' => [
                'sum_assured' => 'GBP',
                'start_value' => 'GBP',
                'premium_amount' => 'GBP',
            ],
            'mortgages' => [
                'original_loan_amount' => 'GBP',
                'outstanding_balance' => 'GBP',
                'monthly_payment' => 'GBP',
                'monthly_interest_portion' => 'GBP',
            ],
            'net_worth_statements' => [
                'total_assets' => 'GBP',
                'total_liabilities' => 'GBP',
                'net_worth' => 'GBP',
            ],
            'payments' => [
                'amount' => 'GBP',
            ],
            'personal_accounts' => [
                'amount' => 'GBP',
            ],
            'properties' => [
                'purchase_price' => 'GBP',
                'current_value' => 'GBP',
                'sdlt_paid' => 'GBP',
                'monthly_rental_income' => 'GBP',
                'outstanding_mortgage' => 'GBP',
                'total_mortgage_balance' => 'GBP',
                'managing_agent_fee' => 'GBP',
                'monthly_council_tax' => 'GBP',
                'monthly_gas' => 'GBP',
                'monthly_electricity' => 'GBP',
                'monthly_water' => 'GBP',
                'monthly_building_insurance' => 'GBP',
                'monthly_contents_insurance' => 'GBP',
                'monthly_service_charge' => 'GBP',
                'monthly_maintenance_reserve' => 'GBP',
                'other_monthly_costs' => 'GBP',
                'annual_service_charge' => 'GBP',
                'annual_ground_rent' => 'GBP',
                'annual_insurance' => 'GBP',
                'annual_maintenance_reserve' => 'GBP',
                'other_annual_costs' => 'GBP',
            ],
            'protection_profiles' => [
                'annual_income' => 'GBP',
                'monthly_expenditure' => 'GBP',
                'mortgage_balance' => 'GBP',
                'other_debts' => 'GBP',
                'group_ci_amount' => 'GBP',
            ],
            'rebalancing_actions' => [
                'trade_value' => 'GBP',
                'current_price' => 'GBP',
                'target_value' => 'GBP',
                'cgt_cost_basis' => 'GBP',
                'cgt_gain_or_loss' => 'GBP',
                'cgt_liability' => 'GBP',
                'executed_price' => 'GBP',
            ],
            'recommendation_tracking' => [
                'recommended_amount' => 'GBP',
            ],
            'retirement_profiles' => [
                'current_annual_salary' => 'GBP',
                'target_retirement_income' => 'GBP',
                'essential_expenditure' => 'GBP',
                'lifestyle_expenditure' => 'GBP',
                'care_cost_annual' => 'GBP',
            ],
            'risk_metrics' => [
                'portfolio_value' => 'GBP',
                'var_95_1month' => 'GBP',
                'cvar_95_1month' => 'GBP',
                'var_99_1month' => 'GBP',
                'cvar_99_1month' => 'GBP',
            ],
            'savings_accounts' => [
                'current_balance' => 'GBP',
                'isa_subscription_amount' => 'GBP',
                'regular_contribution_amount' => 'GBP',
                'planned_lump_sum_amount' => 'GBP',
            ],
            'savings_goals' => [
                'target_amount' => 'GBP',
                'current_saved' => 'GBP',
                'auto_transfer_amount' => 'GBP',
            ],
            'sickness_illness_policies' => [
                'benefit_amount' => 'GBP',
                'premium_amount' => 'GBP',
            ],
            'state_pensions' => [
                'state_pension_forecast_annual' => 'GBP',
                'gap_fill_cost' => 'GBP',
            ],
            'subscriptions' => [
                'amount' => 'GBP',
            ],
            'trusts' => [
                'initial_value' => 'GBP',
                'current_value' => 'GBP',
                'discount_amount' => 'GBP',
                'retained_income_annual' => 'GBP',
                'loan_amount' => 'GBP',
                'sum_assured' => 'GBP',
                'annual_premium' => 'GBP',
                'last_periodic_charge_amount' => 'GBP',
                'total_asset_value' => 'GBP',
            ],
            'users' => [
                'annual_employment_income' => 'GBP',
                'annual_self_employment_income' => 'GBP',
                'annual_rental_income' => 'GBP',
                'annual_dividend_income' => 'GBP',
                'annual_interest_income' => 'GBP',
                'annual_other_income' => 'GBP',
                'annual_trust_income' => 'GBP',
                'monthly_expenditure' => 'GBP',
                'annual_expenditure' => 'GBP',
                'food_groceries' => 'GBP',
                'transport_fuel' => 'GBP',
                'healthcare_medical' => 'GBP',
                'insurance' => 'GBP',
                'mobile_phones' => 'GBP',
                'internet_tv' => 'GBP',
                'subscriptions' => 'GBP',
                'clothing_personal_care' => 'GBP',
                'entertainment_dining' => 'GBP',
                'holidays_travel' => 'GBP',
                'pets' => 'GBP',
                'childcare' => 'GBP',
                'school_fees' => 'GBP',
                'school_lunches' => 'GBP',
                'school_extras' => 'GBP',
                'university_fees' => 'GBP',
                'children_activities' => 'GBP',
                'gifts_charity' => 'GBP',
                'regular_savings' => 'GBP',
                'other_expenditure' => 'GBP',
                'rent' => 'GBP',
                'utilities' => 'GBP',
                'annual_charitable_donations' => 'GBP',
            ],
        ];
    }

    /**
     * Get money columns for a specific table.
     *
     * @return array<string, string> column => currency
     */
    public static function forTable(string $table): array
    {
        return self::all()[$table] ?? [];
    }

    /**
     * Get all table names that have money columns.
     *
     * @return list<string>
     */
    public static function tables(): array
    {
        return array_keys(self::all());
    }

    /**
     * Get total count of money columns across all tables.
     */
    public static function count(): int
    {
        $count = 0;
        foreach (self::all() as $columns) {
            $count += count($columns);
        }

        return $count;
    }

    /**
     * Check if a specific column in a table is a registered money column.
     */
    public static function isMoney(string $table, string $column): bool
    {
        return isset(self::all()[$table][$column]);
    }
}
