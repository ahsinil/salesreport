<?php

use App\Models\Sale;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RoleAndPermissionSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('sales users can visit the dashboard and see their permitted quick access cards', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $salesUser = User::factory()->create();
    $salesUser->assignRole('sales');

    Sale::factory()->for($salesUser)->create([
        'customer_name' => 'Pelanggan Cepat',
    ]);

    $this->actingAs($salesUser);

    $response = $this->get('/dashboard');
    $response
        ->assertOk()
        ->assertSee('Laporan Penjualan')
        ->assertSee('Dasbor')
        ->assertSee('Akses cepat')
        ->assertSee('Menu utama dalam satu baris kartu.')
        ->assertSee('Kelola harga, stok, dan data produk untuk transaksi.')
        ->assertSee('Tinjau semua transaksi dan detail item dari setiap penjualan.')
        ->assertDontSee('Kelola akun tim sales dan rate komisi agent.')
        ->assertDontSee('Buka rekap periode dan unduh file Excel.')
        ->assertSee('Penjualan hari ini')
        ->assertSee('Pendapatan bulan ini')
        ->assertSee('Produk unggulan')
        ->assertSee('Penjualan terbaru')
        ->assertSee('Entri penjualan');
});

test('dashboard sections follow detailed permissions', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permissions::ViewLatestSales);

    Sale::factory()->for($viewer)->create([
        'customer_name' => 'Transaksi Terbaru',
    ]);

    $this->actingAs($viewer);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Penjualan terbaru')
        ->assertSee('Transaksi Terbaru')
        ->assertDontSee('Penjualan hari ini')
        ->assertDontSee('Produk unggulan')
        ->assertDontSee('Kelola harga, stok, dan data produk untuk transaksi.');
});

test('admin users can see all quick access menu cards on the dashboard', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Kelola akun tim sales dan rate komisi agent.')
        ->assertSee('Buka rekap periode dan unduh file Excel.');
});
