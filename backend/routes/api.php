<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\OfferImageController;
use App\Http\Controllers\ReservationController;

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

    Route::delete('offers/{offer}/images/{image}',[OfferImageController::class, 'destroy']);
    Route::post('offers/{offer}/images',[OfferImageController::class, 'store']);

    Route::get('favorites',[FavoriteController::class, 'index']);
    Route::post('offers/{offer}/favorite', [FavoriteController::class, 'store']);
    Route::delete('offers/{offer}/favorite',[FavoriteController::class, 'destroy']);
    Route::post('offers/{offer}/reservations',[ReservationController::class, 'store']);


    Route::get('user', [AuthController::class, 'user']);

    Route::post('logout', [AuthController::class, 'logout']);
});

