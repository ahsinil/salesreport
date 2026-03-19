<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;

test('demo data seeder creates approved sales users and sample sales', function () {
    $this->seed(DemoDataSeeder::class);

    expect(Product::query()->count())->toBeGreaterThan(200);
    expect(User::query()->role('sales')->count())->toBe(3);
    expect(User::query()->role('sales')->where('is_approved', true)->count())->toBe(3);
    expect(Sale::query()->count())->toBeGreaterThan(100);
    expect(Sale::query()->with('user')->get()->every(fn (Sale $sale): bool => $sale->user?->hasRole('sales') ?? false))->toBeTrue();
});
