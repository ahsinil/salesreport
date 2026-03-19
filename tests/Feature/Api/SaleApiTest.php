<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\SaleService;
use App\Support\Permissions;
use Database\Seeders\RoleAndPermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('users with the create sales permission can create multi item sales through the api', function () {
    $salesUser = User::factory()->create();
    $salesUser->givePermissionTo(Permissions::CreateSales);
    $firstProduct = Product::factory()->create(['price' => 25, 'stock' => 10]);
    $secondProduct = Product::factory()->create(['price' => 40, 'stock' => 9]);

    Sanctum::actingAs($salesUser);

    $this->postJson(route('api.sales.store'), [
        'customer_name' => 'Acme Retail',
        'items' => [
            ['product_id' => $firstProduct->id, 'qty' => 2],
            ['product_id' => $secondProduct->id, 'qty' => 3],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.customer_name', 'Acme Retail')
        ->assertJsonPath('data.total_amount', 170)
        ->assertJsonPath('data.commission_amount', 8.5)
        ->assertJsonPath('data.item_count', 5);

    expect($firstProduct->fresh()->stock)->toBe(8);
    expect($secondProduct->fresh()->stock)->toBe(6);
});

test('users with the sales list permission can search sales and see item details through the api', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permissions::ViewSalesList);

    $salesperson = User::factory()->create([
        'name' => 'Rina Sales',
    ]);

    $tea = Product::factory()->create([
        'name' => 'Atlas Tea',
    ]);

    $coffee = Product::factory()->create([
        'name' => 'Bento Coffee',
    ]);

    $saleAlpha = Sale::factory()->for($salesperson)->create([
        'customer_name' => 'Pelanggan Alpha',
    ]);
    $saleBeta = Sale::factory()->for($salesperson)->create([
        'customer_name' => 'Pelanggan Beta',
    ]);

    SaleItem::factory()->for($saleAlpha)->for($tea)->create([
        'qty' => 2,
        'price' => 10,
        'subtotal' => 20,
    ]);
    SaleItem::factory()->for($saleBeta)->for($coffee)->create([
        'qty' => 1,
        'price' => 20,
        'subtotal' => 20,
    ]);

    Sanctum::actingAs($viewer);

    $this->getJson(route('api.sales.index', ['search' => 'atlas']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.customer_name', 'Pelanggan Alpha')
        ->assertJsonPath('data.0.items.0.product_name', 'Atlas Tea');
});

test('admins can void sales and restore stock through the api', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $salesUser = User::factory()->create();
    $product = Product::factory()->create(['price' => 55, 'stock' => 12]);
    $sale = app(SaleService::class)->createSale($salesUser, 'Voidable API Sale', [
        ['product_id' => $product->id, 'qty' => 4],
    ]);

    expect($product->fresh()->stock)->toBe(8);

    Sanctum::actingAs($admin);

    $this->deleteJson(route('api.sales.destroy', $sale))
        ->assertOk()
        ->assertJsonPath('message', 'Penjualan berhasil dibatalkan.');

    expect(Sale::query()->count())->toBe(0);
    expect($product->fresh()->stock)->toBe(12);
});
