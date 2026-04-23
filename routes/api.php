<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\Store\CategoryController;
use App\Http\Controllers\Store\CustomerController;
use App\Http\Controllers\Store\ProductController;
use App\Http\Controllers\Store\StoreController;
use App\Http\Controllers\SubscriptionController;
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

    Route::group(['prefix' => 'dashboard'], function () {
        Route::get('/metrics', [DashboardController::class, 'metrics']);
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
        Route::get('/metrics', [StoreController::class, 'metrics']);
        Route::get('/stocks-graph', [StoreController::class, 'stocksGraph']);
        Route::get('/sales-graph', [StoreController::class, 'salesGraph']);
        Route::get('/transfers-graph', [StoreController::class, 'transfersGraph']);
        Route::get('/{id}', [StoreController::class, 'show']);
        Route::post('/', [StoreController::class, 'store']);
        Route::post('/update/{id}', [StoreController::class, 'update']);
        Route::get('/toggle-status/{id}', [StoreController::class, 'toggleStatus']);
        Route::delete('/{id}', [StoreController::class, 'destroy']);
        Route::post('/{store}/products/add', [StoreController::class, 'addProduct']);
        Route::post('/{store}/members/add', [StoreController::class, 'addMember']);
    });

    Route::group(['prefix' => 'products'], function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/top-selling', [ProductController::class, 'topProducts']);
        Route::get('/category-graph', [ProductController::class, 'categoryGraph']);
        // Route::get('/{id}', [ProductController::class, 'show'])->middleware('permission:view_product_details');
        Route::get('/{id}', [ProductController::class, 'show']);
        Route::post('/', [ProductController::class, 'store'])->middleware('permission:create_product')->middleware('limit:create_product');
        Route::post('/update/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy'])->middleware('permission:delete_product');
    });

    Route::group(['prefix' => 'inventories'], function () {
        Route::get('/', [InventoryController::class, 'index']);
        Route::post('/find', [InventoryController::class, 'find']);
        Route::post('/transfer', [InventoryController::class, 'transfer'])->middleware('permission:create_transfer');
        Route::get('/transfers', [InventoryController::class, 'transfers']);
        Route::get('/movements', [InventoryController::class, 'movements']);
        Route::post('/adjustment', [InventoryController::class, 'adjustment'])->middleware('permission:adjust_inventory');
        Route::get('/metrics', [InventoryController::class, 'metrics']);
        Route::get('/transfer-metrics', [InventoryController::class, 'transferMetrics']);
        Route::get('/movement-metrics', [InventoryController::class, 'movementMetrics']);
        Route::get('/status-graph', [InventoryController::class, 'statusGraph']);
        Route::get('/category-value-graph', [InventoryController::class, 'valueByCategoryGraph']);
        Route::get('/movement-levels-graph', [InventoryController::class, 'movementLevelsGraph']);
        Route::get('/movement-types-graph', [InventoryController::class, 'movementTypesGraph']);
        Route::get('/transfer-levels-graph', [InventoryController::class, 'transferLevelsGraph']);
        Route::get('/transfer-stores-graph', [InventoryController::class, 'transfersByStoresGraph']);
    });

    Route::group(['prefix' => 'customers'], function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::post('/', [CustomerController::class, 'store']);
    });

    Route::group(['prefix' => 'sales'], function () {
        Route::get('/', [SaleController::class, 'index']);
        Route::get('/products', [SaleController::class, 'getProductsForSale']);
        Route::get('/pos-metrics', [SaleController::class, 'posMetrics']);
        Route::get('/order-metrics', [SaleController::class, 'orderMetrics']);
        Route::get('/graph', [SaleController::class, 'graph']);
        Route::get('/revenue-graph', [SaleController::class, 'revenueGraph']);
        Route::get('/status-graph', [SaleController::class, 'statusGraph']);
        Route::get('/payment-status-graph', [SaleController::class, 'paymentStatusGraph']);
        Route::get('/payment-graph', [SaleController::class, 'paymentMethodGraph']);
        Route::get('/{sale}', [SaleController::class, 'show'])->middleware('permission:view_sale_details');
        Route::post('/', [SaleController::class, 'store'])->middleware('permission:create_sale');
        Route::post('/{sale}/complete', [SaleController::class, 'complete'])->middleware('permission:create_sale');
        Route::post('/{sale}/cancel', [SaleController::class, 'cancel'])->middleware('permission:cancel_sale');
        Route::post('/{sale}/refund', [SaleController::class, 'refund'])->middleware('permission:refund_sale');
    });


    Route::group(['prefix' => 'notifications'], function () {
        Route::get('/', [NotificationsController::class, 'index']);
        Route::get('/unread-count', [NotificationsController::class, 'unreadCount']);
        Route::get('/{id}/read', [NotificationsController::class, 'markAsRead']);
    });

    Route::group(['prefix' => 'subscriptions'], function () {
        Route::get('/plans', [SubscriptionController::class, 'plans']);
        Route::post('/', [SubscriptionController::class, 'subscribe']);
    });
});
