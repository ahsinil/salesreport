<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalAmount = fake()->randomFloat(2, 100, 5000);

        return [
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'total_amount' => $totalAmount,
            'commission_amount' => round($totalAmount * 0.05, 2),
        ];
    }
}
