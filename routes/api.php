<?php

use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Store\CategoryController;
use App\Http\Controllers\Store\PosController;
use App\Http\Controllers\Store\ProductController;
use App\Http\Controllers\Store\StoreController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return ['message' => 'API route working'];
});

require __DIR__ . '/../routes/auth.php';

Route::middleware(['auth:sanctum'])->group(function () {
    Route::group(['prefix' => 'users'], function () {
        Route::get('/', [UserController::class, 'index']);
    });

    Route::group(['prefix' => 'roles'], function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::post('/', [RoleController::class, 'store']);
        Route::post('/update/{role}', [RoleController::class, 'update']);
        Route::delete('/{role}', [RoleController::class, 'destroy']);
        Route::post('/assign', [RoleController::class, 'assign']);
        Route::post('/remove', [RoleController::class, 'remove']);
    });

    Route::group(['prefix' => 'permissions'], function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::get('/{id}', [PermissionController::class, 'show']);
        Route::post('/', [PermissionController::class, 'store']);
        Route::post('/update/{permission}', [PermissionController::class, 'update']);
        Route::delete('/{permission}', [PermissionController::class, 'destroy']);
        Route::post('/assign-to-role', [PermissionController::class, 'assignToRole']);
        Route::post('/remove-from-role', [PermissionController::class, 'removeFromRole']);
    });

    Route::group(['prefix' => 'categories'], function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{id}', [CategoryController::class, 'show']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::post('/update/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
    });

    Route::group(['prefix' => 'stores'], function () {
        Route::get('/', [StoreController::class, 'index']);
        Route::get('/{id}', [StoreController::class, 'show']);
        Route::post('/', [StoreController::class, 'store']);
        Route::post('/update/{id}', [StoreController::class, 'update']);
        Route::get('/toggle-status/{id}', [StoreController::class, 'toggleStatus']);
        Route::delete('/{id}', [StoreController::class, 'destroy']);
        Route::post('/{store}/products/add', [StoreController::class, 'addProduct']);
    });

    Route::group(['prefix' => 'products'], function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{id}', [ProductController::class, 'show']);
        Route::post('/', [ProductController::class, 'store']);
        Route::post('/update/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
    });

    Route::group(['prefix' => 'inventories'], function () {
        Route::get('/', [InventoryController::class, 'index']);
        Route::get('/movements', [InventoryController::class, 'movements']);
    });

    Route::group(['prefix' => 'pos'], function () {
        Route::get('/products', [PosController::class, 'getProducts']);
        Route::get('/customers', [PosController::class, 'getCustomers']);
    });
});
