<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
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
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'member_id' => now()->format('YmdHis').fake()->unique()->numberBetween(1000, 9999),
            'name' => $firstName,
            'lastname' => $lastName,
            'pseudo' => fake()->userName(),
            'telephone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'gender' => fake()->randomElement(['M', 'F']),
            'password' => static::$password ??= Hash::make('password'),
            'username' => fake()->unique()->userName(),
            'date' => now(),
            'categorie_id' => 7,
            'parent_code' => 0,
            'sponsor_code' => 0,
            'e_mobile_number' => '',
            'bank_name' => '',
            'bank_account' => '',
            'total_amount_e_wallet' => 0,
            'password_e_wallet' => static::$password,
            'inscription_mode' => 'factory',
            'member_statute' => 'enabled',
            'actual_level' => 0,
            'pdfpaquet' => 0,
            'adress' => fake()->address(),
            'city' => null,
        ];
    }

    /**
     * Indicate that the member account should be disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'member_statute' => 'disabled',
        ]);
    }
}