<?php

use App\Http\Controllers\Api\BusinessTypeController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('business-types', [BusinessTypeController::class, 'index']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('products', [ProductController::class, 'index']);

    Route::apiResource('orders', OrderController::class)->except(['create', 'edit']);
    Route::post('orders/{order}/complete', [OrderController::class, 'complete']);
});
