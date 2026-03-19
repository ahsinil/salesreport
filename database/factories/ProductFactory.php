<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->randomFloat(2, 10, 500),
            'commission_rate' => null,
            'stock' => fake()->numberBetween(5, 150),
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn (): array => [
            'stock' => fake()->numberBetween(0, 5),
        ]);
    }
}
