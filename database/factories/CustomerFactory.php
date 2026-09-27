<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_type_id' => CustomerType::factory(),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'city' => fake()->city(),
        ];
    }
}
