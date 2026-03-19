<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RoleAndPermissionSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('sales list page is forbidden without the sales list permission', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('sales.index'))->assertForbidden();
});

test('users with the sales list permission can view all sales with item details', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permissions::ViewSalesList);

    $salesperson = User::factory()->create([
        'name' => 'Dian Sales',
    ]);

    $sale = Sale::factory()->for($salesperson)->create([
        'customer_name' => 'Toko Maju',
        'date' => '2026-03-12',
        'total_amount' => 60000,
        'commission_amount' => 3000,
    ]);

    $soap = Product::factory()->create([
        'name' => 'Sabun Batang',
    ]);

    $shampoo = Product::factory()->create([
        'name' => 'Shampo Herbal',
    ]);

    SaleItem::factory()->for($sale)->for($soap)->create([
        'qty' => 2,
        'price' => 12000,
        'subtotal' => 24000,
    ]);

    SaleItem::factory()->for($sale)->for($shampoo)->create([
        'qty' => 3,
        'price' => 12000,
        'subtotal' => 36000,
    ]);

    $this->actingAs($viewer);

    $this->get(route('sales.index'))
        ->assertOk()
        ->assertSee('Daftar penjualan')
        ->assertSee('Toko Maju')
        ->assertSee('Dian Sales')
        ->assertSee('Sabun Batang')
        ->assertSee('Shampo Herbal')
        ->assertSee('Rp. 60.000')
        ->assertSee('Rp. 3.000');
});

test('authorized users can search the sales list by customer salesperson or product', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permissions::ViewSalesList);

    $rina = User::factory()->create([
        'name' => 'Rina Sales',
    ]);

    $budi = User::factory()->create([
        'name' => 'Budi Lapangan',
    ]);

    $atlasTea = Product::factory()->create([
        'name' => 'Atlas Tea',
    ]);

    $bentoCoffee = Product::factory()->create([
        'name' => 'Bento Coffee',
    ]);

    $saleAlpha = Sale::factory()->for($rina)->create([
        'customer_name' => 'Pelanggan Alpha',
        'date' => '2026-03-10',
    ]);

    $saleBeta = Sale::factory()->for($budi)->create([
        'customer_name' => 'Pelanggan Beta',
        'date' => '2026-03-11',
    ]);

    SaleItem::factory()->for($saleAlpha)->for($atlasTea)->create();
    SaleItem::factory()->for($saleBeta)->for($bentoCoffee)->create();

    $this->actingAs($viewer);

    Volt::test('sales.index')
        ->set('search', 'atlas')
        ->assertSee('Pelanggan Alpha')
        ->assertDontSee('Pelanggan Beta')
        ->set('search', 'budi')
        ->assertSee('Pelanggan Beta')
        ->assertDontSee('Pelanggan Alpha')
        ->set('search', 'alpha')
        ->assertSee('Pelanggan Alpha')
        ->assertDontSee('Pelanggan Beta');
});
