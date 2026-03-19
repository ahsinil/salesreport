<?php

use App\Http\Controllers\Api\Admin\CommissionSettingController;
use App\Http\Controllers\Api\Admin\SalesAccountController;
use App\Http\Controllers\Api\Admin\SalesPermissionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Resources\UserResource;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'store'])->name('api.auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'show'])->name('api.auth.me');
        Route::post('logout', [AuthController::class, 'destroy'])->name('api.auth.logout');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', function (Request $request): UserResource {
        return new UserResource($request->user());
    })->name('api.user');

    Route::get('dashboard', DashboardController::class)->name('api.dashboard');

    Route::controller(ProductController::class)->prefix('products')->group(function () {
        Route::get('/', 'index')
            ->middleware('can:'.Permissions::ViewProducts)
            ->name('api.products.index');
        Route::post('/', 'store')
            ->middleware('can:'.Permissions::CreateProducts)
            ->name('api.products.store');
        Route::get('{product}', 'show')
            ->middleware('can:'.Permissions::ViewProducts)
            ->name('api.products.show');
        Route::match(['put', 'patch'], '{product}', 'update')
            ->middleware('can:'.Permissions::EditProducts)
            ->name('api.products.update');
        Route::delete('{product}', 'destroy')
            ->middleware('can:'.Permissions::DeleteProducts)
            ->name('api.products.destroy');
    });

    Route::controller(SaleController::class)->prefix('sales')->group(function () {
        Route::get('/', 'index')
            ->middleware('can:'.Permissions::ViewSalesList)
            ->name('api.sales.index');
        Route::post('/', 'store')
            ->middleware('can:'.Permissions::CreateSales)
            ->name('api.sales.store');
        Route::get('{sale}', 'show')
            ->middleware('can:'.Permissions::ViewSalesList)
            ->name('api.sales.show');
        Route::delete('{sale}', 'destroy')
            ->middleware('admin')
            ->name('api.sales.destroy');
    });

    Route::controller(ReportController::class)->prefix('reports/sales')->middleware('can:'.Permissions::ViewReports)->group(function () {
        Route::get('/', 'index')->name('api.reports.sales.index');
        Route::get('export', 'export')->name('api.reports.sales.export');
    });

    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::controller(SalesAccountController::class)->prefix('sales-accounts')->group(function () {
            Route::get('/', 'index')->name('api.admin.sales-accounts.index');
            Route::post('/', 'store')->name('api.admin.sales-accounts.store');
            Route::get('{salesAccount}', 'show')->name('api.admin.sales-accounts.show');
            Route::match(['put', 'patch'], '{salesAccount}', 'update')->name('api.admin.sales-accounts.update');
            Route::delete('{salesAccount}', 'destroy')->name('api.admin.sales-accounts.destroy');
            Route::patch('{salesAccount}/approve', 'approve')->name('api.admin.sales-accounts.approve');
        });

        Route::controller(CommissionSettingController::class)->prefix('commission-settings')->group(function () {
            Route::get('/', 'show')->name('api.admin.commission-settings.show');
            Route::match(['put', 'patch'], '/', 'update')->name('api.admin.commission-settings.update');
        });

        Route::controller(SalesPermissionController::class)->prefix('permissions')->group(function () {
            Route::get('sales', 'show')->name('api.admin.permissions.sales.show');
            Route::match(['put', 'patch'], 'sales', 'update')->name('api.admin.permissions.sales.update');
            Route::post('sales/reset', 'resetSales')->name('api.admin.permissions.sales.reset');
            Route::post('admin/reset', 'resetAdmin')->name('api.admin.permissions.admin.reset');
        });
    });
});
