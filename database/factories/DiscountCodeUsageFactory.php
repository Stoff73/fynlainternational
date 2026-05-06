<?php

declare(strict_types=1);

namespace Database\Factories;

use Fynla\Core\Models\DiscountCode;
use Fynla\Core\Models\DiscountCodeUsage;
use Fynla\Core\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountCodeUsage>
 */
class DiscountCodeUsageFactory extends Factory
{
    protected $model = DiscountCodeUsage::class;

    public function definition(): array
    {
        return [
            'discount_code_id' => DiscountCode::factory(),
            'user_id' => User::factory(),
            'payment_id' => Payment::factory(),
            'applied_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
