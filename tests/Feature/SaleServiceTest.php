<?php

use App\Models\CommissionSetting;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Validation\ValidationException;

test('sale creation is rolled back when a later item has insufficient stock', function () {
    $user = User::factory()->create();
    $firstProduct = Product::factory()->create(['price' => 25, 'stock' => 10]);
    $secondProduct = Product::factory()->create(['price' => 40, 'stock' => 1]);

    expect(fn () => app(SaleService::class)->createSale($user, 'Rollback Customer', [
        ['product_id' => $firstProduct->id, 'qty' => 2],
        ['product_id' => $secondProduct->id, 'qty' => 3],
    ]))->toThrow(ValidationException::class);

    expect(Sale::query()->count())->toBe(0);
    expect($firstProduct->fresh()->stock)->toBe(10);
    expect($secondProduct->fresh()->stock)->toBe(1);
});

test('voiding a sale restores stock and removes the sale records', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 55, 'stock' => 12]);
    $service = app(SaleService::class);

    $sale = $service->createSale($user, 'Voidable Sale', [
        ['product_id' => $product->id, 'qty' => 4],
    ]);

    expect($product->fresh()->stock)->toBe(8);

    $service->voidSale($sale);

    expect(Sale::query()->count())->toBe(0);
    expect($product->fresh()->stock)->toBe(12);
});

test('sale creation uses the assigned agent commission rate when the basis is agent', function () {
    CommissionSetting::factory()->create([
        'basis' => 'agent',
        'default_rate' => 5,
    ]);

    $salesUser = User::factory()->create([
        'commission_rate' => 8,
    ]);
    $product = Product::factory()->create([
        'price' => 100,
        'stock' => 10,
    ]);

    $sale = app(SaleService::class)->createSale($salesUser, 'Agent Basis', [
        ['product_id' => $product->id, 'qty' => 2],
    ]);

    expect($sale->total_amount)->toBe('200.00');
    expect($sale->commission_amount)->toBe('16.00');
});

test('sale creation uses product commission rates when the basis is product', function () {
    CommissionSetting::factory()->create([
        'basis' => 'product',
        'default_rate' => 5,
    ]);

    $salesUser = User::factory()->create([
        'commission_rate' => 12,
    ]);
    $featuredProduct = Product::factory()->create([
        'price' => 100,
        'stock' => 10,
        'commission_rate' => 10,
    ]);
    $standardProduct = Product::factory()->create([
        'price' => 40,
        'stock' => 10,
        'commission_rate' => null,
    ]);

    $sale = app(SaleService::class)->createSale($salesUser, 'Product Basis', [
        ['product_id' => $featuredProduct->id, 'qty' => 1],
        ['product_id' => $standardProduct->id, 'qty' => 2],
    ]);

    expect($sale->total_amount)->toBe('180.00');
    expect($sale->commission_amount)->toBe('14.00');
});
