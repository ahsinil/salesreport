<?php

use App\Models\CommissionSetting;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admins can view the sales account manager page', function () {
    $this->actingAs($this->admin);

    $this->get(route('sales-accounts.index'))
        ->assertOk()
        ->assertSee('Akun sales')
        ->assertSee('Pengaturan komisi')
        ->assertDontSee('Hak akses sales')
        ->assertSee('flex flex-wrap gap-3 lg:justify-end', false)
        ->assertSee('Akun sales baru')
        ->assertDontSee('↕')
        ->assertDontSee('↑')
        ->assertDontSee('↓');
});

test('non admins can not view the sales account manager page', function () {
    $salesUser = User::factory()->create();
    $salesUser->assignRole('sales');

    $this->actingAs($salesUser);

    $this->get(route('sales-accounts.index'))->assertForbidden();
});

test('admins can create sales accounts', function () {
    $this->actingAs($this->admin);

    Volt::test('sales-accounts.index')
        ->assertSet('showSalesAccountModal', false)
        ->call('startCreating')
        ->assertSet('showSalesAccountModal', true)
        ->set('name', 'Sales Baru')
        ->set('email', 'sales-baru@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->set('commissionRate', '7.50')
        ->call('save')
        ->assertSet('showSalesAccountModal', false)
        ->assertHasNoErrors();

    $salesUser = User::query()->where('email', 'sales-baru@example.com')->first();

    expect($salesUser)->not->toBeNull();
    expect($salesUser->name)->toBe('Sales Baru');
    expect($salesUser->hasRole('sales'))->toBeTrue();
    expect($salesUser->is_approved)->toBeTrue();
    expect($salesUser->commission_rate)->toBe('7.50');
});

test('admins can update sales accounts', function () {
    $salesUser = User::factory()->create([
        'name' => 'Sales Lama',
        'email' => 'sales-lama@example.com',
    ]);
    $salesUser->assignRole('sales');

    $this->actingAs($this->admin);

    Volt::test('sales-accounts.index')
        ->call('edit', $salesUser->id)
        ->assertSet('showSalesAccountModal', true)
        ->set('name', 'Sales Utama')
        ->set('email', 'sales-utama@example.com')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->set('commissionRate', '9.25')
        ->call('save')
        ->assertSet('showSalesAccountModal', false)
        ->assertHasNoErrors();

    $salesUser->refresh();

    expect($salesUser->name)->toBe('Sales Utama');
    expect($salesUser->email)->toBe('sales-utama@example.com');
    expect($salesUser->hasRole('sales'))->toBeTrue();
    expect(Hash::check('new-password', $salesUser->password))->toBeTrue();
    expect($salesUser->commission_rate)->toBe('9.25');
});

test('admins can delete sales accounts without transactions', function () {
    $salesUser = User::factory()->create();
    $salesUser->assignRole('sales');

    $this->actingAs($this->admin);

    Volt::test('sales-accounts.index')
        ->call('delete', $salesUser->id)
        ->assertSee('Akun sales berhasil dihapus.');

    expect($salesUser->fresh())->toBeNull();
});

test('sales accounts with recorded transactions can not be deleted', function () {
    $salesUser = User::factory()->create();
    $salesUser->assignRole('sales');

    $sale = Sale::factory()->for($salesUser)->create();

    $this->actingAs($this->admin);

    Volt::test('sales-accounts.index')
        ->call('delete', $salesUser->id)
        ->assertSee('Akun sales yang sudah memiliki transaksi tidak dapat dihapus.');

    expect($salesUser->fresh())->not->toBeNull();
    expect($sale->fresh())->not->toBeNull();
});

test('admins can approve pending sales accounts', function () {
    $salesUser = User::factory()->create([
        'is_approved' => false,
    ]);
    $salesUser->assignRole('sales');

    $this->actingAs($this->admin);

    Volt::test('sales-accounts.index')
        ->call('approve', $salesUser->id)
        ->assertSee('Akun sales berhasil disetujui.');

    expect($salesUser->fresh()->is_approved)->toBeTrue();
});

test('admins can update the commission settings', function () {
    $this->actingAs($this->admin);

    Volt::test('sales-accounts.index')
        ->set('commissionBasis', 'product')
        ->set('defaultCommissionRate', '6.50')
        ->call('saveCommissionSettings')
        ->assertHasNoErrors()
        ->assertSee('Pengaturan komisi berhasil diperbarui.');

    $commissionSettings = CommissionSetting::query()->first();

    expect($commissionSettings)->not->toBeNull();
    expect($commissionSettings->basis)->toBe('product');
    expect($commissionSettings->default_rate)->toBe('6.50');
});

test('admins can search sales accounts by name or email', function () {
    $firstSalesUser = User::factory()->create([
        'name' => 'Rina Sales',
        'email' => 'rina@example.com',
    ]);
    $firstSalesUser->assignRole('sales');

    $secondSalesUser = User::factory()->create([
        'name' => 'Budi Lapangan',
        'email' => 'budi@example.com',
    ]);
    $secondSalesUser->assignRole('sales');

    $this->actingAs($this->admin);

    Volt::test('sales-accounts.index')
        ->set('search', 'RiNa')
        ->assertSee('Rina Sales')
        ->assertDontSee('Budi Lapangan')
        ->set('search', 'BUDI@EXAMPLE.COM')
        ->assertSee('Budi Lapangan')
        ->assertDontSee('Rina Sales');
});

test('admins can sort sales accounts by name', function () {
    $firstSalesUser = User::factory()->create([
        'name' => 'Zaki Sales',
    ]);
    $firstSalesUser->assignRole('sales');

    $secondSalesUser = User::factory()->create([
        'name' => 'Andi Sales',
    ]);
    $secondSalesUser->assignRole('sales');

    $this->actingAs($this->admin);

    Volt::test('sales-accounts.index')
        ->call('sort', 'name')
        ->assertSeeInOrder(['Andi Sales', 'Zaki Sales'])
        ->call('sort', 'name')
        ->assertSeeInOrder(['Zaki Sales', 'Andi Sales']);
});
