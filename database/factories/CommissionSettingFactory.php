<?php

namespace Database\Factories;

use App\Models\CommissionSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionSetting>
 */
class CommissionSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'basis' => 'agent',
            'default_rate' => 5,
        ];
    }
}
