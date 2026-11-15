<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Api\Admin\MediaController;
use App\Http\Controllers\Api\Admin\ShopCategoryController;
use App\Http\Controllers\Api\Admin\ShopOrderController as AdminShopOrderController;
use App\Http\Controllers\Api\Admin\ShopProductController;
use App\Http\Controllers\Api\Admin\ShopSettingController;
use App\Http\Controllers\Api\Admin\SiteSettingController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ShopCatalogController;
use App\Http\Controllers\Api\ShopOrderController;
use App\Http\Controllers\Api\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::get('/site', SiteController::class);

Route::get('/shop/catalog', [ShopCatalogController::class, 'catalog']);
Route::get('/shop/config', [ShopCatalogController::class, 'config']);
Route::get('/shop/products/{slug}', [ShopCatalogController::class, 'product'])
    ->where('slug', '[a-z0-9-]+');
Route::post('/shop/orders', [ShopOrderController::class, 'store'])->middleware('throttle:10,15');
Route::post('/leads', [LeadController::class, 'store'])->middleware('throttle:10,15');

Route::prefix('admin')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,15');

    Route::middleware('admin.token')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        // Заявки
        Route::get('/leads', [AdminLeadController::class, 'index']);
        Route::get('/leads/stats', [AdminLeadController::class, 'stats']);
        Route::get('/leads/{id}', [AdminLeadController::class, 'show'])->whereNumber('id');
        Route::patch('/leads/{id}', [AdminLeadController::class, 'update'])->whereNumber('id');
        Route::delete('/leads/{id}', [AdminLeadController::class, 'destroy'])->whereNumber('id');

        // Настройки сайта и магазина
        Route::get('/settings/site', [SiteSettingController::class, 'show']);
        Route::put('/settings/site', [SiteSettingController::class, 'update']);
        Route::patch('/settings/site', [SiteSettingController::class, 'patch']);

        Route::get('/shop/settings', [ShopSettingController::class, 'show']);
        Route::put('/shop/settings', [ShopSettingController::class, 'update']);

        // Сводка для бейджа и стартового экрана (старый адрес фронта)
        Route::get('/shop/dashboard', [AdminShopOrderController::class, 'dashboard']);

        // Категории
        Route::get('/shop/categories', [ShopCategoryController::class, 'index']);
        Route::post('/shop/categories', [ShopCategoryController::class, 'store']);
        Route::put('/shop/categories/reorder', [ShopCategoryController::class, 'reorder']);
        Route::patch('/shop/categories/{id}', [ShopCategoryController::class, 'update'])->whereNumber('id');
        Route::delete('/shop/categories/{id}', [ShopCategoryController::class, 'destroy'])->whereNumber('id');

        // Товары
        Route::get('/shop/products', [ShopProductController::class, 'index']);
        Route::post('/shop/products', [ShopProductController::class, 'store']);
        Route::put('/shop/products/reorder', [ShopProductController::class, 'reorder']);
        Route::patch('/shop/products/{id}', [ShopProductController::class, 'update'])->whereNumber('id');
        Route::delete('/shop/products/{id}', [ShopProductController::class, 'destroy'])->whereNumber('id');

        // Заказы
        Route::get('/shop/orders', [AdminShopOrderController::class, 'index']);
        Route::get('/shop/orders/stats', [AdminShopOrderController::class, 'stats']);
        Route::get('/shop/orders/dashboard', [AdminShopOrderController::class, 'dashboard']);
        Route::get('/shop/orders/{id}', [AdminShopOrderController::class, 'show'])->whereNumber('id');
        Route::patch('/shop/orders/{id}', [AdminShopOrderController::class, 'update'])->whereNumber('id');
        Route::delete('/shop/orders/{id}', [AdminShopOrderController::class, 'destroy'])->whereNumber('id');

        // Загрузки
        Route::get('/media', [MediaController::class, 'index']);
        Route::post('/media', [MediaController::class, 'store']);
        Route::delete('/media', [MediaController::class, 'destroy']);
    });
});
