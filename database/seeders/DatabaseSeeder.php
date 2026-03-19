<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CommissionSettingSeeder::class,
            RoleAndPermissionSeeder::class,
            ProductSeeder::class,
        ]);

        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'is_approved' => true,
                'commission_rate' => null,
            ],
        );

        $user->assignRole('admin');
    }
}
