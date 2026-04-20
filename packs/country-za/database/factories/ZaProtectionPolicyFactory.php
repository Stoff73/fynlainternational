<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Database\Factories;

use App\Models\User;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class ZaProtectionPolicyFactory extends Factory
{
    protected $model = ZaProtectionPolicy::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'joint_owner_id' => null,
            'ownership_percentage' => 100,
            'product_type' => 'life',
            'provider' => $this->faker->randomElement(['Discovery Life', 'Liberty', 'Old Mutual', 'Sanlam', 'Momentum']),
            'policy_number' => strtoupper($this->faker->bothify('??######')),
            'cover_amount_minor' => $this->faker->numberBetween(500_000_00, 10_000_000_00),
            'premium_amount_minor' => $this->faker->numberBetween(500_00, 5_000_00),
            'premium_frequency' => 'monthly',
            'start_date' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'end_date' => null,
            'severity_tier' => null,
            'waiting_period_months' => null,
            'benefit_term_months' => null,
            'group_scheme' => false,
            'notes' => null,
        ];
    }

    public function life(): static
    {
        return $this->state(['product_type' => 'life']);
    }

    public function dread(): static
    {
        return $this->state([
            'product_type' => 'dread',
            'severity_tier' => 'B',
        ]);
    }

    public function incomeProtection(): static
    {
        return $this->state([
            'product_type' => 'idisability_income',
            'waiting_period_months' => 3,
            'benefit_term_months' => 240, // to age 65 rough proxy
        ]);
    }

    public function funeral(): static
    {
        return $this->state([
            'product_type' => 'funeral',
            'cover_amount_minor' => 30_000_00,
            'premium_amount_minor' => 150_00,
        ]);
    }
}
