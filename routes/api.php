<?php

use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Staff Authentication
    |--------------------------------------------------------------------------
    */

    Route::prefix('staff')->group(function () {

        // Public
        Route::post('/login', [StaffAuthController::class, 'login']);

        // Public - refresh token is the credential
        Route::post('/refresh', [StaffAuthController::class, 'refresh']);

        // Protected - requires valid JWT
        Route::middleware('auth:staff')->group(function () {
            Route::post('/logout', [StaffAuthController::class, 'logout']);
        });
    });


    /*
    |--------------------------------------------------------------------------
    | Customer Authentication
    |--------------------------------------------------------------------------
    */

    Route::prefix('customer')->group(function () {

        // Public
        Route::post('/register', [CustomerAuthController::class, 'register']);

        // Public
        Route::post('/login', [CustomerAuthController::class, 'login']);

        // Public - refresh token is the credential
        Route::post('/refresh', [CustomerAuthController::class, 'refresh']);

        // Protected - requires valid JWT
        Route::middleware('auth:customer')->group(function () {
            Route::post('/logout', [CustomerAuthController::class, 'logout']);
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