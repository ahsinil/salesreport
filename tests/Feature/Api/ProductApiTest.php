<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RoleAndPermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('users with the view product permission can list products through the api', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permissions::ViewProducts);

    Product::factory()->create([
        'name' => 'Readonly Product',
    ]);

    Sanctum::actingAs($viewer);

    $this->getJson(route('api.products.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Readonly Product')
        ->assertJsonPath('data.0.commission_rate', null);
});

test('authorized users can create update and delete products through the api', function () {
    $salesUser = User::factory()->create();
    $salesUser->givePermissionTo([
        Permissions::CreateProducts,
        Permissions::EditProducts,
        Permissions::DeleteProducts,
    ]);

    Sanctum::actingAs($salesUser);

    $this->postJson(route('api.products.store'), [
        'name' => 'API Product',
        'price' => 125.5,
        'stock' => 14,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'API Product');

    $product = Product::query()->where('name', 'API Product')->first();

    expect($product)->not->toBeNull();

    $this->putJson(route('api.products.update', $product), [
        'name' => 'API Product Updated',
        'price' => 150,
        'stock' => 8,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'API Product Updated')
        ->assertJsonPath('data.stock', 8);

    $this->deleteJson(route('api.products.destroy', $product))
        ->assertOk()
        ->assertJsonPath('message', 'Produk berhasil dihapus.');

    expect($product->fresh())->toBeNull();
});

test('products tied to sales can not be deleted through the api', function () {
    $salesUser = User::factory()->create();
    $salesUser->givePermissionTo(Permissions::DeleteProducts);
    $product = Product::factory()->create();
    $sale = Sale::factory()->for($salesUser)->create();

    $sale->items()->create([
        'product_id' => $product->id,
        'qty' => 2,
        'price' => 50,
        'subtotal' => 100,
    ]);

    Sanctum::actingAs($salesUser);

    $this->deleteJson(route('api.products.destroy', $product))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Produk yang sudah tercatat dalam penjualan tidak dapat dihapus.');

    expect($product->fresh())->not->toBeNull();
});
