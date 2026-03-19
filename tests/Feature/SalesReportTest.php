<?php

use App\Models\Sale;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RoleAndPermissionSeeder;
use Livewire\Volt\Volt;

test('report page is forbidden without the report permission', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('reports.index'))->assertForbidden();
});

test('authorized users can filter the sales report by date', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();
    $user->givePermissionTo(Permissions::ViewReports);

    Sale::factory()->for($user)->create([
        'customer_name' => 'March Customer',
        'date' => '2026-03-10',
        'total_amount' => 100,
        'commission_amount' => 5,
    ]);

    Sale::factory()->for($user)->create([
        'customer_name' => 'February Customer',
        'date' => '2026-02-10',
        'total_amount' => 80,
        'commission_amount' => 4,
    ]);

    $this->actingAs($user);

    $component = Volt::test('reports.index')
        ->assertDontSee('↕')
        ->assertDontSee('↑')
        ->assertDontSee('↓')
        ->set('startDate', '2026-03-01')
        ->set('endDate', '2026-03-31')
        ->assertSee('March Customer')
        ->assertSee('Rp. 100')
        ->assertSee('Rp. 5')
        ->assertDontSee('February Customer');

    expect($component->instance()->chartSeries())->toBe([
        ['day' => '10 Mar', 'total' => 100.0],
    ]);

    $component
        ->set('startDate', '2026-02-01')
        ->set('endDate', '2026-02-28')
        ->assertSee('February Customer')
        ->assertDontSee('March Customer');

    expect($component->instance()->chartSeries())->toBe([
        ['day' => '10 Feb', 'total' => 80.0],
    ]);

    $component
        ->set('startDate', '2026-01-01')
        ->set('endDate', '2026-01-31')
        ->assertSee('Belum ada penjualan pada rentang tanggal ini.');

    expect($component->instance()->chartSeries())->toBe([]);
});

test('authorized users can export the filtered report', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();
    $user->givePermissionTo(Permissions::ViewReports);

    Sale::factory()->for($user)->create([
        'date' => '2026-03-15',
        'total_amount' => 120,
        'commission_amount' => 6,
    ]);

    $this->actingAs($user);

    Volt::test('reports.index')
        ->set('startDate', '2026-03-01')
        ->set('endDate', '2026-03-31')
        ->call('export')
        ->assertFileDownloaded('laporan-penjualan.xlsx');
});

test('authorized users can search the sales report by customer or salesperson', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $admin = User::factory()->create([
        'name' => 'Manager Toko',
    ]);
    $admin->givePermissionTo(Permissions::ViewReports);

    $salesUser = User::factory()->create([
        'name' => 'Rina Sales',
    ]);

    Sale::factory()->for($admin)->create([
        'customer_name' => 'Pelanggan Alpha',
        'date' => '2026-03-10',
        'total_amount' => 100,
        'commission_amount' => 5,
    ]);

    Sale::factory()->for($salesUser)->create([
        'customer_name' => 'Pelanggan Beta',
        'date' => '2026-03-11',
        'total_amount' => 80,
        'commission_amount' => 4,
    ]);

    $this->actingAs($admin);

    Volt::test('reports.index')
        ->set('startDate', '2026-03-01')
        ->set('endDate', '2026-03-31')
        ->set('search', 'RiNa')
        ->assertSee('Pelanggan Beta')
        ->assertDontSee('Pelanggan Alpha')
        ->set('search', 'AlPhA')
        ->assertSee('Pelanggan Alpha')
        ->assertDontSee('Pelanggan Beta');
});

test('authorized users can sort the sales report by customer name', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();
    $user->givePermissionTo(Permissions::ViewReports);

    Sale::factory()->for($user)->create([
        'customer_name' => 'Zulu Customer',
        'date' => '2026-03-10',
        'total_amount' => 100,
        'commission_amount' => 5,
    ]);

    Sale::factory()->for($user)->create([
        'customer_name' => 'Alpha Customer',
        'date' => '2026-03-11',
        'total_amount' => 80,
        'commission_amount' => 4,
    ]);

    $this->actingAs($user);

    Volt::test('reports.index')
        ->set('startDate', '2026-03-01')
        ->set('endDate', '2026-03-31')
        ->call('sort', 'customer_name')
        ->assertSeeInOrder(['Alpha Customer', 'Zulu Customer'])
        ->call('sort', 'customer_name')
        ->assertSeeInOrder(['Zulu Customer', 'Alpha Customer']);
});
