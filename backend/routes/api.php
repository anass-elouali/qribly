<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\AuthController;

// Public routes
Route::apiResource('categories', CategoryController::class);

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::get('offers', [OfferController::class, 'index']);
Route::get('offers/nearby', [OfferController::class, 'nearby']);
Route::get('offers/{offer}', [OfferController::class, 'show']);


// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('offers', [OfferController::class, 'store']);
    Route::put('offers/{offer}', [OfferController::class, 'update']);
    Route::patch('offers/{offer}', [OfferController::class, 'update']);
    Route::delete('offers/{offer}', [OfferController::class, 'destroy']);

    Route::get('user', [AuthController::class, 'user']);

    Route::post('logout', [AuthController::class, 'logout']);
});

