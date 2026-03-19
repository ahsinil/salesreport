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

test('dashboard api only returns the sections allowed by the users permissions', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permissions::ViewLatestSales);

    Sale::factory()->for($viewer)->create([
        'customer_name' => 'Transaksi Terbaru',
    ]);

    Sanctum::actingAs($viewer);

    $this->getJson(route('api.dashboard'))
        ->assertOk()
        ->assertJsonPath('permissions.view_latest_sales', true)
        ->assertJsonPath('permissions.view_sales_stats', false)
        ->assertJsonPath('stats', null)
        ->assertJsonCount(1, 'recent_sales')
        ->assertJsonPath('recent_sales.0.customer_name', 'Transaksi Terbaru');
});

test('sales role receives dashboard stats and recent sales through the api', function () {
    $salesUser = User::factory()->create();
    $salesUser->assignRole('sales');

    Product::factory()->lowStock()->create([
        'name' => 'Stok Tipis',
    ]);

    Sale::factory()->for($salesUser)->create([
        'customer_name' => 'Pelanggan Cepat',
        'date' => now()->toDateString(),
        'total_amount' => 125,
        'commission_amount' => 6.25,
    ]);

    Sanctum::actingAs($salesUser);

    $this->getJson(route('api.dashboard'))
        ->assertOk()
        ->assertJsonPath('permissions.view_sales_stats', true)
        ->assertJsonPath('permissions.view_latest_sales', true)
        ->assertJsonStructure([
            'stats' => [
                'today_sales',
                'monthly_sales',
                'monthly_commission',
                'monthly_transactions',
                'average_order_value',
                'total_products',
                'low_stock_count',
            ],
            'recent_sales',
            'low_stock_products',
        ]);
});
