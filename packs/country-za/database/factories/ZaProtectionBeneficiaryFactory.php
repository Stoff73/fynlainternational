<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Database\Factories;

use Fynla\Packs\Za\Models\ZaProtectionBeneficiary;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class ZaProtectionBeneficiaryFactory extends Factory
{
    protected $model = ZaProtectionBeneficiary::class;

    public function definition(): array
    {
        return [
            'policy_id' => ZaProtectionPolicy::factory(),
            'beneficiary_type' => 'spouse',
            'name' => $this->faker->name(),
            'relationship' => 'spouse',
            'allocation_percentage' => 100,
            'id_number' => null,
        ];
    }

    public function estate(): static
    {
        return $this->state([
            'beneficiary_type' => 'estate',
            'name' => null,
            'relationship' => null,
            'id_number' => null,
        ]);
    }

    public function nominated(): static
    {
        return $this->state([
            'beneficiary_type' => 'nominated_individual',
            'id_number' => $this->faker->numerify('#############'),
        ]);
    }
}
