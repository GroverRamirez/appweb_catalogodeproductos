<?php

use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImportProductsController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'two_factor', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('categories', CategoryController::class)
            ->except(['show'])
            ->middlewareFor('index', 'permission:categories.view')
            ->middlewareFor(['create', 'store'], 'permission:categories.create')
            ->middlewareFor(['edit', 'update'], 'permission:categories.update')
            ->middlewareFor('destroy', 'permission:categories.delete');

        Route::resource('brands', BrandController::class)
            ->except(['show'])
            ->middlewareFor('index', 'permission:brands.view')
            ->middlewareFor(['create', 'store'], 'permission:brands.create')
            ->middlewareFor(['edit', 'update'], 'permission:brands.update')
            ->middlewareFor('destroy', 'permission:brands.delete');

        // Must be registered BEFORE the resource to avoid {product} wildcard conflict
        Route::post('products/import', ImportProductsController::class)
            ->middleware('permission:products.create')
            ->name('products.import');

        Route::resource('products', ProductController::class)
            ->except(['show'])
            ->middlewareFor('index', 'permission:products.view')
            ->middlewareFor(['create', 'store'], 'permission:products.create')
            ->middlewareFor(['edit', 'update'], 'permission:products.update')
            ->middlewareFor('destroy', 'permission:products.delete');

        Route::resource('banners', BannerController::class)
            ->except(['show'])
            ->middlewareFor('index', 'permission:banners.view')
            ->middlewareFor(['create', 'store'], 'permission:banners.create')
            ->middlewareFor(['edit', 'update'], 'permission:banners.update')
            ->middlewareFor('destroy', 'permission:banners.delete');

        Route::resource('coupons', CouponController::class)
            ->except(['show'])
            ->middlewareFor('index', 'permission:coupons.view')
            ->middlewareFor(['create', 'store'], 'permission:coupons.create')
            ->middlewareFor(['edit', 'update'], 'permission:coupons.update')
            ->middlewareFor('destroy', 'permission:coupons.delete');

        Route::get('inquiries', [InquiryController::class, 'index'])
            ->middleware('permission:inquiries.view')
            ->name('inquiries.index');
        Route::get('inquiries/export.csv', [InquiryController::class, 'export'])
            ->middleware('permission:inquiries.view')
            ->name('inquiries.export');
        Route::get('inquiries/{inquiry}', [InquiryController::class, 'show'])
            ->middleware('permission:inquiries.view')
            ->name('inquiries.show');
        Route::patch('inquiries/{inquiry}', [InquiryController::class, 'update'])
            ->middleware('permission:inquiries.update')
            ->name('inquiries.update');
        Route::post('inquiries/{inquiry}/notas', [InquiryController::class, 'storeNote'])
            ->middleware('permission:inquiries.update')
            ->name('inquiries.notes.store');
        Route::delete('inquiries/{inquiry}', [InquiryController::class, 'destroy'])
            ->middleware('permission:inquiries.delete')
            ->name('inquiries.destroy');

        Route::resource('roles', RoleController::class)
            ->except(['show'])
            ->middleware('role:propietario')
            ->middlewareFor('index', 'permission:roles.view')
            ->middlewareFor(['create', 'store'], 'permission:roles.create')
            ->middlewareFor(['edit', 'update'], 'permission:roles.update')
            ->middlewareFor('destroy', 'permission:roles.delete');

        Route::resource('users', UserController::class)
            ->except(['show'])
            ->middleware('role:propietario')
            ->middlewareFor('index', 'permission:users.view')
            ->middlewareFor(['create', 'store'], 'permission:users.create')
            ->middlewareFor(['edit', 'update'], 'permission:users.update')
            ->middlewareFor('destroy', 'permission:users.delete');

        Route::get('reportes', [ReportController::class, 'index'])
            ->middleware('permission:reports.view')
            ->name('reports.index');
        Route::get('reportes/export.csv', [ReportController::class, 'export'])
            ->middleware('permission:reports.view')
            ->name('reports.export');

        Route::get('settings', [SettingsController::class, 'edit'])
            ->middleware('permission:settings.view')
            ->name('settings.edit');
        Route::patch('settings', [SettingsController::class, 'update'])
            ->middleware('permission:settings.update')
            ->name('settings.update');
    });
