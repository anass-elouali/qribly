<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\AuthController;

Route::apiResource('categories', CategoryController::class);
Route::apiResource('offers', OfferController::class);
Route::post('register', [AuthController::class, 'register']);