<?php

use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return ['message' => 'API route working'];
});

require __DIR__ . '/../routes/auth.php';

Route::middleware(['auth:sanctum'])->group(function () {
    Route::group(['prefix' => 'roles'], function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::post('/', [RoleController::class, 'store']);
        Route::post('/update/{role}', [RoleController::class, 'update']);
        Route::post('/assign', [RoleController::class, 'assign']);
    });
});
