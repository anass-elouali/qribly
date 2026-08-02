<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OfferController;

Route::apiResource('categories', CategoryController::class);
Route::apiResource('offers', OfferController::class);