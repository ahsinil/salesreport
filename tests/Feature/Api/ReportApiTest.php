<?php

use App\Models\Sale;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RoleAndPermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('report api is forbidden without the report permission', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.reports.sales.index'))->assertForbidden();
});

test('authorized users can fetch filtered report data and export through the api', function () {
    $user = User::factory()->create([
        'name' => 'Manager Report',
    ]);
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

    Sanctum::actingAs($user);

    $this->getJson(route('api.reports.sales.index', [
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ]))
        ->assertOk()
        ->assertJsonPath('summary.total_sales', 100)
        ->assertJsonPath('summary.total_commission', 5)
        ->assertJsonPath('summary.transaction_count', 1)
        ->assertJsonPath('chart.0.day', '10 Mar')
        ->assertJsonPath('sales.data.0.customer_name', 'March Customer');

    $this->get(route('api.reports.sales.export', [
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ]))->assertDownload('laporan-penjualan.xlsx');
});
