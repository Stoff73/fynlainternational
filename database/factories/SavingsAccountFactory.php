<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SavingsAccount>
 */
class SavingsAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'account_type' => fake()->randomElement(['easy_access', 'notice', 'fixed_rate']),
            'institution' => fake()->company(),
            'account_number' => fake()->numerify('########'),
            'current_balance' => fake()->randomFloat(2, 100, 50000),
            'interest_rate' => fake()->randomFloat(4, 0.01, 0.05),
            'access_type' => fake()->randomElement(['immediate', 'notice', 'fixed']),
            'notice_period_days' => null,
            'maturity_date' => null,
            'is_isa' => false,
            'isa_type' => null,
            'isa_subscription_year' => null,
            'isa_subscription_amount' => null,
        ];
    }

    /**
     * SA Tax-Free Savings Account state. Sets country_code='ZA', flips
     * is_tfsa, and seeds sensible subscription + lifetime figures in ZAR.
     */
    public function tfsa(): static
    {
        return $this->state(fn () => [
            'country_code' => 'ZA',
            'is_tfsa' => true,
            'is_isa' => false,
            'tfsa_subscription_year' => '2026/27',
            'tfsa_subscription_amount_minor' => 2_000_000,
            'tfsa_subscription_amount_ccy' => 'ZAR',
            'tfsa_lifetime_contributed_minor' => 2_000_000,
            'tfsa_lifetime_contributed_ccy' => 'ZAR',
            'account_type' => 'tfsa',
            'account_name' => 'TFSA — Investec',
        ]);
    }

    /**
     * Minor TFSA — held by an adult on behalf of a FamilyMember child.
     * The beneficiary columns are already on savings_accounts; this state
     * populates them so minor-TFSA tracking keys correctly.
     */
    public function minor(\App\Models\FamilyMember $beneficiary): static
    {
        return $this->state(fn () => [
            'beneficiary_id' => $beneficiary->id,
            'beneficiary_name' => $beneficiary->full_name ?? 'Minor beneficiary',
            'beneficiary_dob' => $beneficiary->date_of_birth ?? '2020-01-01',
            'account_name' => 'TFSA — Minor (' . ($beneficiary->full_name ?? 'child') . ')',
        ]);
    }
}
