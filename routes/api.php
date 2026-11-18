<?php

use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\StaffController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::prefix('staff')->group(function () {
        Route::post('/login', [StaffAuthController::class, 'login']);

        Route::post('/refresh', [StaffAuthController::class, 'refresh']);

        Route::post('/logout', [StaffAuthController::class, 'logout']);

        Route::middleware('auth:staff')->group(function () {
            Route::get('/me', [StaffAuthController::class, 'me']);
        });
    });

    Route::prefix('customer')->group(function () {
        Route::post('/register', [CustomerAuthController::class, 'register']);

        Route::post('/login', [CustomerAuthController::class, 'login']);

        Route::post('/refresh', [CustomerAuthController::class, 'refresh']);

        Route::post('/logout', [CustomerAuthController::class, 'logout']);

        Route::middleware('auth:customer')->group(function () {
            Route::get('/me', [CustomerAuthController::class, 'me']);
        });
    });
});

Route::prefix('staff')
    ->middleware('auth:staff')
    ->group(function () {
        Route::get('/', [StaffController::class, 'index']);
        Route::post('/', [StaffController::class, 'store']);

        Route::get('/{staff}', [StaffController::class, 'show']);
        Route::put('/{staff}', [StaffController::class, 'update']);
        Route::delete('/{staff}', [StaffController::class, 'destroy']);

        Route::post(
            '/{staff}/roles',
            [StaffController::class, 'assignRole']
        );

        Route::delete(
            '/{staff}/roles',
            [StaffController::class, 'removeRole']
        );

        Route::post(
            '/{staff}/permissions',
            [StaffController::class, 'assignPermission']
        );

        Route::delete(
            '/{staff}/permissions',
            [StaffController::class, 'revokePermission']
        );
    });

Route::middleware('auth:staff')->group(function () {
    Route::get('/roles', [PermissionController::class, 'roles']);
    Route::get('/permissions', [PermissionController::class, 'permissions']);
});
