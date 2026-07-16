<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Fynla\Core\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.3)->firstName(),
            'surname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'marital_status' => 'single',
        ];
    }

    /**
     * Assign a specific primary jurisdiction (e.g. ->jurisdiction('GB') /
     * ->jurisdiction('ZA')). Opt-in: the factory deliberately does NOT assign a
     * default jurisdiction, so bare users stay row-less (WS1 enforcement
     * fail-opens for row-less users, so existing pack-route tests keep passing).
     */
    public function jurisdiction(string $code): static
    {
        return $this->afterCreating(function (\Fynla\Core\Models\User $user) use ($code) {
            (new \Fynla\Core\Jurisdiction\AssignPrimaryJurisdiction)->assign($user, $code);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
