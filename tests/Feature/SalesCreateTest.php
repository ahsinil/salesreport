<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RoleAndPermissionSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('sales entry page is forbidden without the create sales permission', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('sales.create'))->assertForbidden();
});

test('users with the create sales permission can view the sales entry page', function () {
    $salesUser = User::factory()->create();
    $salesUser->givePermissionTo(Permissions::CreateSales);

    $this->actingAs($salesUser);

    $this->get(route('sales.create'))
        ->assertOk()
        ->assertSee('Estimasi total')
        ->assertSee('Estimasi komisi')
        ->assertSee('wire:model.live="items.0.product_id"', false)
        ->assertSee('wire:model.live.debounce.150ms="items.0.qty"', false);
});

test('estimated total and commission update when line items change', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permissions::CreateSales);
    $product = Product::factory()->create(['price' => 25, 'stock' => 10]);

    $this->actingAs($user);

    Volt::test('sales.create')
        ->assertSee('Rp. 0')
        ->set('items.0.product_id', (string) $product->id)
        ->set('items.0.qty', '3')
        ->assertSee('Rp. 75')
        ->assertSee('Rp. 3,75');
});

test('a sale with multiple items can be created and stock is decremented', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permissions::CreateSales);
    $firstProduct = Product::factory()->create(['price' => 25, 'stock' => 10]);
    $secondProduct = Product::factory()->create(['price' => 40, 'stock' => 9]);

    $this->actingAs($user);

    Volt::test('sales.create')
        ->set('customerName', 'Acme Retail')
        ->set('items', [
            ['product_id' => (string) $firstProduct->id, 'qty' => '2'],
            ['product_id' => (string) $secondProduct->id, 'qty' => '3'],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Penjualan berhasil dicatat.');

    $sale = Sale::query()->with('items')->first();

    expect($sale)->not->toBeNull();
    expect($sale->customer_name)->toBe('Acme Retail');
    expect($sale->items)->toHaveCount(2);
    expect($sale->total_amount)->toBe('170.00');
    expect($sale->commission_amount)->toBe('8.50');
    expect($firstProduct->fresh()->stock)->toBe(8);
    expect($secondProduct->fresh()->stock)->toBe(6);
});

test('insufficient stock fails gracefully and leaves inventory unchanged', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permissions::CreateSales);
    $product = Product::factory()->create(['stock' => 2]);

    $this->actingAs($user);

    Volt::test('sales.create')
        ->set('customerName', 'Short Supply')
        ->set('items', [
            ['product_id' => (string) $product->id, 'qty' => '5'],
        ])
        ->call('save')
        ->assertHasErrors(['items']);

    expect(Sale::query()->count())->toBe(0);
    expect($product->fresh()->stock)->toBe(2);
});
