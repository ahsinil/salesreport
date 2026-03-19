<?php

use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RoleAndPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admins can view and update commission settings through the api', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson(route('api.admin.commission-settings.show'))
        ->assertOk()
        ->assertJsonPath('data.basis', 'agent');

    $this->patchJson(route('api.admin.commission-settings.update'), [
        'basis' => 'product',
        'default_rate' => 6.5,
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Pengaturan komisi berhasil diperbarui.')
        ->assertJsonPath('data.basis', 'product')
        ->assertJsonPath('data.default_rate', 6.5);
});

test('admins can manage permissions and reset admin access through the api', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson(route('api.admin.permissions.sales.show'))
        ->assertOk()
        ->assertJsonPath('data.sales.available_permissions.0.enabled', true);

    $this->patchJson(route('api.admin.permissions.sales.update'), [
        'permissions' => [
            Permissions::ViewProducts,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Hak akses sales berhasil diperbarui.')
        ->assertJsonPath('data.sales.granted_permissions.0', Permissions::ViewProducts);

    $this->postJson(route('api.admin.permissions.sales.reset'))
        ->assertOk()
        ->assertJsonPath('message', 'Hak akses sales dikembalikan ke bawaan.');

    Role::findByName('admin', 'web')->syncPermissions([]);

    $this->getJson(route('api.products.index'))->assertForbidden();

    $this->postJson(route('api.admin.permissions.admin.reset'))
        ->assertOk()
        ->assertJsonPath('message', 'Hak akses admin dikembalikan ke bawaan.');

    $this->getJson(route('api.products.index'))->assertOk();
});
