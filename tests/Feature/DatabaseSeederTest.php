<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('database seeder creates the default admin account and base data', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user?->name)->toBe('Test User');
    expect($user?->is_approved)->toBeTrue();
    expect($user?->hasRole('admin'))->toBeTrue();
    expect(Product::query()->count())->toBeGreaterThan(0);
});
