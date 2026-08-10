<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Models\Offer;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{

    public function index(Request $request) {
        $reservations = $request->user()
          ->reservations()
            ->with('offer')
            ->latest('scheduled_at')
            ->paginate(15);

        return ReservationResource::collection($reservations);
    }


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

    public function cancel(Request $request, Reservation $reservation)
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not allowed to cancel this reservation.',
            ], 403);
        }

        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'This reservation cannot be cancelled.',
            ], 422);
        }

        $reservation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);


        return response()->json([
            'message' => 'Reservation cancelled successfully.',
            'reservation' => [
                'id' => $reservation->id,
                'status' => $reservation->status,
                'cancelled_at' => $reservation->cancelled_at,
            ],
        ]);
    }
}