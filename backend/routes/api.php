<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OfferImageController;
use App\Http\Controllers\ProviderAvailabilityController;
use App\Http\Controllers\ProviderServiceRequestController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ServiceRequestAssistantController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\ServiceRequestProposalController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Public routes
Route::apiResource('categories', CategoryController::class);

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::get('offers', [OfferController::class, 'index']);
Route::get('offers/nearby', [OfferController::class, 'nearby']);
Route::get('offers/smart-search', [OfferController::class, 'smartSearch']);
Route::get('offers/{offer}/availability', [ProviderAvailabilityController::class, 'offer']);
Route::get('offers/{offer}', [OfferController::class, 'show']);

// Public Review
Route::get('offers/{offer}/reviews', [ReviewController::class, 'index']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('offers', [OfferController::class, 'store']);
    Route::put('offers/{offer}', [OfferController::class, 'update']);
    Route::patch('offers/{offer}', [OfferController::class, 'update']);
    Route::delete('offers/{offer}', [OfferController::class, 'destroy']);

    Route::delete('offers/{offer}/images/{image}', [OfferImageController::class, 'destroy']);
    Route::post('offers/{offer}/images', [OfferImageController::class, 'store']);

    Route::get('favorites', [FavoriteController::class, 'index']);
    Route::post('offers/{offer}/favorite', [FavoriteController::class, 'store']);
    Route::delete('offers/{offer}/favorite', [FavoriteController::class, 'destroy']);

    // Client Reservations Routes
    Route::get('reservations', [ReservationController::class, 'index']);
    Route::post('offers/{offer}/reservations', [ReservationController::class, 'store']);
    Route::patch('reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);

    // Intelligent service requests
    Route::post('assistant/interpret-service-request', ServiceRequestAssistantController::class)
        ->middleware('throttle:10,1');
    Route::get('service-requests', [ServiceRequestController::class, 'index']);
    Route::post('service-requests', [ServiceRequestController::class, 'store']);
    Route::get('service-requests/{serviceRequest}', [ServiceRequestController::class, 'show']);
    Route::patch('service-requests/{serviceRequest}/cancel', [ServiceRequestController::class, 'cancel']);
    Route::post('service-request-proposals/{proposal}/accept', [ServiceRequestProposalController::class, 'accept']);
    Route::patch('service-request-proposals/{proposal}/decline', [ServiceRequestProposalController::class, 'decline']);

    // Provider Reservations Routes
    Route::get('provider/reservations', [ReservationController::class, 'providerIndex']);
    Route::get('provider/availability', [ProviderAvailabilityController::class, 'show']);
    Route::put('provider/availability', [ProviderAvailabilityController::class, 'update']);
    Route::patch('provider/reservations/{reservation}/confirm', [ReservationController::class, 'providerConfirm']);
    Route::patch('provider/reservations/{reservation}/cancel', [ReservationController::class, 'providerCancel']);
    Route::patch('provider/reservations/{reservation}/complete', [ReservationController::class, 'providerComplete']);
    Route::get('provider/service-requests', [ProviderServiceRequestController::class, 'index']);
    Route::put('provider/service-requests/{serviceRequest}/proposal', [ProviderServiceRequestController::class, 'upsertProposal']);
    Route::patch('provider/service-request-proposals/{proposal}/withdraw', [ProviderServiceRequestController::class, 'withdrawProposal']);

    // Reviews Routes
    Route::post('reviews', [ReviewController::class, 'store']);
    Route::patch('reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy']);

    // Notifications Routes
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Broadcasting auth (private channels) — registered here so it lives under
    // api/broadcasting/auth: covered by CORS's `api/*` rule and authenticated
    // via the same Bearer token as the rest of the API, unlike the default
    // session-based /broadcasting/auth route.
    Broadcast::routes(['middleware' => ['auth:sanctum']]);

    // Conversations Routes
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store']);

    // Messages Routes
    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store']);
    Route::patch('messages/{message}/read', [MessageController::class, 'markAsRead']);

    Route::get('user', [AuthController::class, 'user']);
    Route::patch('user', [AuthController::class, 'updateProfile']);
    Route::post('logout', [AuthController::class, 'logout']);
});
