<?php

use App\Models\CommissionSetting;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('product manager page is forbidden without the view product permission', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('products.index'))->assertForbidden();
});

test('users with only the view product permission can browse the product manager page', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permissions::ViewProducts);

    Product::factory()->create([
        'name' => 'Readonly Product',
    ]);

    $this->actingAs($viewer);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertDontSee('Produk baru')
        ->assertSee('Readonly Product')
        ->assertDontSee('Ubah')
        ->assertDontSee('Hapus')
        ->assertDontSee('↕')
        ->assertDontSee('↑')
        ->assertDontSee('↓');
});

test('tailwind paginator matches the app theme', function () {
    $paginator = new LengthAwarePaginator(
        collect(range(1, 20))->forPage(1, 10),
        20,
        10,
        1,
        ['path' => '/products'],
    );

    $markup = $paginator->links('vendor.pagination.tailwind')->render();

    expect($markup)
        ->toContain('Navigasi halaman')
        ->toContain('rounded-full border border-zinc-950 bg-zinc-950')
        ->toContain('border-zinc-300 bg-white');
});

test('products can be created and updated', function () {
    $salesUser = User::factory()->create();
    $salesUser->givePermissionTo([
        Permissions::ViewProducts,
        Permissions::CreateProducts,
        Permissions::EditProducts,
    ]);

    $this->actingAs($salesUser);

    Volt::test('products.index')
        ->assertSet('showProductModal', false)
        ->call('startCreating')
        ->assertSet('showProductModal', true)
        ->set('name', 'Widget Prime')
        ->set('price', '125.50')
        ->set('stock', '14')
        ->call('save')
        ->assertSet('showProductModal', false)
        ->assertHasNoErrors();

    $product = Product::query()->first();

    expect($product)->not->toBeNull();
    expect($product->name)->toBe('Widget Prime');
    expect($product->stock)->toBe(14);

    Volt::test('products.index')
        ->call('edit', $product->id)
        ->assertSet('showProductModal', true)
        ->set('price', '150.00')
        ->set('stock', '8')
        ->call('save')
        ->assertSet('showProductModal', false)
        ->assertHasNoErrors();

    expect($product->fresh()->price)->toBe('150.00');
    expect($product->fresh()->stock)->toBe(8);
});

test('product validation errors are shown', function () {
    $salesUser = User::factory()->create();
    $salesUser->givePermissionTo([
        Permissions::ViewProducts,
        Permissions::CreateProducts,
    ]);

    $this->actingAs($salesUser);

    Volt::test('products.index')
        ->set('name', '')
        ->set('price', '-10')
        ->set('stock', '-1')
        ->call('save')
        ->assertHasErrors(['name', 'price', 'stock']);
});

test('users without the create product permission can not open the create form', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permissions::ViewProducts);

    $this->actingAs($viewer);

    Volt::test('products.index')
        ->call('startCreating')
        ->assertForbidden();
});

test('users without the edit product permission can not edit products', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permissions::ViewProducts);
    $product = Product::factory()->create();

    $this->actingAs($viewer);

    Volt::test('products.index')
        ->call('edit', $product->id)
        ->assertForbidden();
});

test('users without the delete product permission can not delete products', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permissions::ViewProducts);
    $product = Product::factory()->create();

    $this->actingAs($viewer);

    Volt::test('products.index')
        ->call('delete', $product->id)
        ->assertForbidden();
});

test('products tied to sales can not be deleted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permissions::ViewProducts,
        Permissions::DeleteProducts,
    ]);
    $product = Product::factory()->create();
    $sale = Sale::factory()->for($user)->create();

    $sale->items()->create([
        'product_id' => $product->id,
        'qty' => 2,
        'price' => 99.99,
        'subtotal' => 199.98,
    ]);

    $this->actingAs($user);

    Volt::test('products.index')
        ->call('delete', $product->id)
        ->assertSee('Produk yang sudah tercatat dalam penjualan tidak dapat dihapus.');

    expect($product->fresh())->not->toBeNull();
});

test('admins can set a product commission rate', function () {
    CommissionSetting::factory()->create([
        'basis' => 'product',
        'default_rate' => 5,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin);

    Volt::test('products.index')
        ->call('startCreating')
        ->set('name', 'Komisi Produk')
        ->set('price', '200')
        ->set('stock', '12')
        ->set('commissionRate', '11.50')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->where('name', 'Komisi Produk')->first();

    expect($product)->not->toBeNull();
    expect($product->commission_rate)->toBe('11.50');
});

test('non admins can not change product commission rates', function () {
    $product = Product::factory()->create([
        'commission_rate' => '9.00',
    ]);
    $salesUser = User::factory()->create();
    $salesUser->givePermissionTo([
        Permissions::ViewProducts,
        Permissions::EditProducts,
    ]);

    $this->actingAs($salesUser);

    Volt::test('products.index')
        ->call('edit', $product->id)
        ->set('commissionRate', '15.00')
        ->call('save')
        ->assertHasNoErrors();

    expect($product->fresh()->commission_rate)->toBe('9.00');
});

test('products can be searched by name', function () {
    $salesUser = User::factory()->create();
    $salesUser->givePermissionTo(Permissions::ViewProducts);

    $this->actingAs($salesUser);

    Product::factory()->create([
        'name' => 'Widget Alpha',
    ]);

    Product::factory()->create([
        'name' => 'Cable Beta',
    ]);

    Volt::test('products.index')
        ->set('search', 'WiDgEt')
        ->assertSee('Widget Alpha')
        ->assertDontSee('Cable Beta');
});

test('products can be sorted by name', function () {
    $salesUser = User::factory()->create();
    $salesUser->givePermissionTo(Permissions::ViewProducts);

    $this->actingAs($salesUser);

    Product::factory()->create([
        'name' => 'Zulu Cable',
    ]);

    Product::factory()->create([
        'name' => 'Alpha Cable',
    ]);

    Volt::test('products.index')
        ->call('sort', 'name')
        ->assertSeeInOrder(['Alpha Cable', 'Zulu Cable'])
        ->call('sort', 'name')
        ->assertSeeInOrder(['Zulu Cable', 'Alpha Cable']);
});
