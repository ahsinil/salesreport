<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admins can list and create sales accounts through the api', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson(route('api.admin.sales-accounts.index'))
        ->assertOk()
        ->assertJsonStructure([
            'summary' => [
                'sales_account_count',
                'active_sales_account_count',
                'pending_sales_account_count',
                'default_commission_rate',
            ],
            'sales_accounts',
        ]);

    $this->postJson(route('api.admin.sales-accounts.store'), [
        'name' => 'Sales API',
        'email' => 'sales-api@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'commission_rate' => 7.5,
    ])
        ->assertCreated()
        ->assertJsonPath('message', 'Akun sales berhasil dibuat.')
        ->assertJsonPath('data.email', 'sales-api@example.com')
        ->assertJsonPath('data.is_approved', true);

    $salesUser = User::query()->where('email', 'sales-api@example.com')->first();

    expect($salesUser)->not->toBeNull();
    expect($salesUser->hasRole('sales'))->toBeTrue();
});

test('admins can approve sales accounts through the api', function () {
    $salesUser = User::factory()->create([
        'is_approved' => false,
    ]);
    $salesUser->assignRole('sales');

    Sanctum::actingAs($this->admin);

    $this->patchJson(route('api.admin.sales-accounts.approve', $salesUser))
        ->assertOk()
        ->assertJsonPath('message', 'Akun sales berhasil disetujui.')
        ->assertJsonPath('data.is_approved', true);

    expect($salesUser->fresh()->is_approved)->toBeTrue();
});

test('non admins can not access the sales account api', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.admin.sales-accounts.index'))->assertForbidden();
});
