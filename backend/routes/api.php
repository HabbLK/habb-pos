<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessTypeController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\RegisterSessionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ---- Public ----
    Route::post('auth/login', [AuthController::class, 'login']);

    // ---- Any logged-in user (cashier or admin) ----
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('business-types', [BusinessTypeController::class, 'index']);
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('products', [ProductController::class, 'index']);

        Route::apiResource('orders', OrderController::class)->except(['create', 'edit']);
        Route::post('orders/{order}/complete', [OrderController::class, 'complete']);
        Route::post('orders/{order}/void', [OrderController::class, 'void']);

        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);

        Route::get('register-sessions/current', [RegisterSessionController::class, 'current']);
        Route::post('register-sessions/open', [RegisterSessionController::class, 'open']);
        Route::post('register-sessions/{registerSession}/close', [RegisterSessionController::class, 'close']);

        // ---- Admin only ----
        Route::middleware('role:admin')->group(function () {
            Route::post('products', [ProductController::class, 'store']);
            Route::patch('products/{product}', [ProductController::class, 'update']);
            Route::post('products/{product}/adjust-stock', [ProductController::class, 'adjustStock']);

            Route::get('suppliers', [SupplierController::class, 'index']);
            Route::post('suppliers', [SupplierController::class, 'store']);

            Route::get('purchases', [PurchaseController::class, 'index']);
            Route::post('purchases', [PurchaseController::class, 'store']);
            Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive']);

            Route::get('expenses', [ExpenseController::class, 'index']);
            Route::post('expenses', [ExpenseController::class, 'store']);
            Route::get('expense-categories', [ExpenseController::class, 'categories']);

            Route::get('reports/summary', [ReportController::class, 'summary']);
            Route::get('reports/top-products', [ReportController::class, 'topProducts']);
            Route::get('reports/low-stock', [ReportController::class, 'lowStock']);
        });
    });
});
