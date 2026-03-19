<?php

namespace Database\Seeders;

use App\Models\CommissionSetting;
use Illuminate\Database\Seeder;

class CommissionSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (CommissionSetting::query()->exists()) {
            return;
        }

        CommissionSetting::query()->create([
            'basis' => 'agent',
            'default_rate' => 5,
        ]);
    }
}
