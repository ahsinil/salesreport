<?php

use App\Models\CommissionSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\CommissionService;

test('agent commission uses the sales account rate when the basis is agent', function () {
    CommissionSetting::factory()->create([
        'basis' => 'agent',
        'default_rate' => 5,
    ]);

    $salesUser = User::factory()->create([
        'commission_rate' => 8,
    ]);

    expect(app(CommissionService::class)->calculate($salesUser, [
        ['subtotal' => 200],
    ]))->toBe(16.0);
});

test('product commission uses product rates with the default as fallback', function () {
    CommissionSetting::factory()->create([
        'basis' => 'product',
        'default_rate' => 5,
    ]);

    $salesUser = User::factory()->create();
    $featuredProduct = Product::factory()->create([
        'commission_rate' => 10,
    ]);
    $standardProduct = Product::factory()->create([
        'commission_rate' => null,
    ]);

    expect(app(CommissionService::class)->calculate($salesUser, [
        ['product' => $featuredProduct, 'subtotal' => 100],
        ['product' => $standardProduct, 'subtotal' => 80],
    ]))->toBe(14.0);
});
