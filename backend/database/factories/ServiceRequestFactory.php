<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => \App\Models\User::factory(),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => 'pending',
            'latitude' => 24.7136 + ($this->faker->randomFloat(6, -0.1, 0.1)),
            'longitude' => 46.6753 + ($this->faker->randomFloat(6, -0.1, 0.1)),
        ];
    }
}
