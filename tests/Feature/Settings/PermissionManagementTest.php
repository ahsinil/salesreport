<?php

use App\Models\Sale;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RoleAndPermissionSeeder;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admin users can see the sales permissions item in settings navigation', function () {
    $this->actingAs($this->admin);

    $this->get(route('settings.profile'))
        ->assertOk()
        ->assertSee('Hak akses sales');
});

test('admins can view the sales permissions settings page', function () {
    $this->actingAs($this->admin);

    $this->get(route('settings.permissions'))
        ->assertOk()
        ->assertSee('Pengaturan')
        ->assertSee('Hak akses sales')
        ->assertSee('Lihat produk')
        ->assertSee('Buat produk')
        ->assertSee('Ubah produk')
        ->assertSee('Hapus produk')
        ->assertSee('Lihat daftar penjualan')
        ->assertSee('Lihat statistik penjualan')
        ->assertSee('Lihat penjualan terbaru')
        ->assertSee('Reset ke bawaan')
        ->assertSee('Reset hak akses admin');
});

test('non admins can not view the sales permissions settings page', function () {
    $salesUser = User::factory()->create();
    $salesUser->assignRole('sales');

    $this->actingAs($salesUser);

    $this->get(route('settings.permissions'))->assertForbidden();
    $this->get(route('settings.profile'))->assertDontSee('Hak akses sales');
});

test('admins can update sales permissions from settings and the changes affect sales access', function () {
    $salesUser = User::factory()->create();
    $salesUser->assignRole('sales');

    Sale::factory()->for($salesUser)->create([
        'customer_name' => 'Invoice Baru',
    ]);

    $this->actingAs($this->admin);

    Volt::test('settings.permissions')
        ->set('salesPermissions', [
            Permissions::ViewProducts,
            Permissions::ViewLatestSales,
        ])
        ->call('saveSalesPermissions')
        ->assertHasNoErrors()
        ->assertSee('Hak akses sales berhasil diperbarui.');

    $this->actingAs($salesUser->fresh());

    $this->get(route('products.index'))->assertOk();
    $this->get(route('sales.index'))->assertForbidden();
    $this->get(route('sales.create'))->assertForbidden();
    $this->get(route('reports.index'))->assertForbidden();
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Penjualan terbaru')
        ->assertSee('Invoice Baru')
        ->assertDontSee('Penjualan hari ini');
});

test('admins can reset sales permissions to the default set', function () {
    $salesUser = User::factory()->create();
    $salesUser->assignRole('sales');

    Sale::factory()->for($salesUser)->create([
        'customer_name' => 'Invoice Reset',
    ]);

    $this->actingAs($this->admin);

    Volt::test('settings.permissions')
        ->set('salesPermissions', [
            Permissions::ViewProducts,
        ])
        ->call('saveSalesPermissions')
        ->assertHasNoErrors()
        ->call('resetSalesPermissions')
        ->assertHasNoErrors()
        ->assertSee('Hak akses sales dikembalikan ke bawaan.');

    $this->actingAs($salesUser->fresh());

    $this->get(route('products.index'))->assertOk();
    $this->get(route('sales.index'))->assertOk();
    $this->get(route('sales.create'))->assertOk();
    $this->get(route('reports.index'))->assertForbidden();
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Penjualan hari ini')
        ->assertSee('Penjualan terbaru')
        ->assertSee('Invoice Reset');
});

test('admins can reset admin permissions to the default set', function () {
    Role::findByName('admin', 'web')->syncPermissions([]);

    $this->actingAs($this->admin->fresh());

    $this->get(route('products.index'))->assertForbidden();
    $this->get(route('sales.index'))->assertForbidden();
    $this->get(route('sales.create'))->assertForbidden();
    $this->get(route('reports.index'))->assertForbidden();

    Volt::test('settings.permissions')
        ->call('resetAdminPermissions')
        ->assertHasNoErrors()
        ->assertSee('Hak akses admin dikembalikan ke bawaan.');

    $this->actingAs($this->admin->fresh());

    $this->get(route('products.index'))->assertOk();
    $this->get(route('sales.index'))->assertOk();
    $this->get(route('sales.create'))->assertOk();
    $this->get(route('reports.index'))->assertOk();
});
