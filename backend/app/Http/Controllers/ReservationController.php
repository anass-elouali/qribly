<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Models\Offer;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;

class ReservationController extends Controller
{
    public function store(
        StoreReservationRequest $request,
        Offer $offer
    ) {
        if ($offer->type !== 'service') {
            return response()->json([
                'message' => 'Only service offers can be reserved.',
            ], 422);
        }

        $reservation = $request->user()
            ->reservations()
            ->create([
                'offer_id' => $offer->id,
                'scheduled_at' => $request->validated('scheduled_at'),
                'notes' => $request->validated('notes'),
                'status' => 'pending',
            ]);

        $reservation->load([
            'user',
            'offer',
        ]);

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }
}