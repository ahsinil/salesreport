<?php

use App\Support\Permissions;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('products', 'products.index')
        ->middleware('can:'.Permissions::ViewProducts)
        ->name('products.index');
    Volt::route('sales-accounts', 'sales-accounts.index')->name('sales-accounts.index');
    Volt::route('sales', 'sales.index')
        ->middleware('can:'.Permissions::ViewSalesList)
        ->name('sales.index');
    Volt::route('sales/create', 'sales.create')
        ->middleware('can:'.Permissions::CreateSales)
        ->name('sales.create');
    Volt::route('reports', 'reports.index')
        ->middleware('can:'.Permissions::ViewReports)
        ->name('reports.index');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/permissions', 'settings.permissions')->name('settings.permissions');
});

require __DIR__.'/auth.php';
