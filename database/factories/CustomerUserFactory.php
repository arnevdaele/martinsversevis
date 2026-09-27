<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerUser> */
class CustomerUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ];
    }

    public function invited(): static
    {
        return $this->state(['password' => null, 'invited_at' => now()]);
    }
}
