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
        Route::post('/login', [StaffAuthController::class, 'login']);
        Route::post('/refresh', [StaffAuthController::class, 'refresh']);
        Route::post('/logout', [StaffAuthController::class, 'logout']);
    });


    /*
    |--------------------------------------------------------------------------
    | Customer Authentication
    |--------------------------------------------------------------------------
    */

    Route::prefix('customer')->group(function () {
        Route::post('/register', [CustomerAuthController::class, 'register']);
        Route::post('/login', [CustomerAuthController::class, 'login']);
        Route::post('/refresh', [CustomerAuthController::class, 'refresh']);
        Route::post('/logout', [CustomerAuthController::class, 'logout']);
    });

});