<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Models\Offer;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\Request;
use App\Notifications\ReservationCreated;
use App\Notifications\ReservationConfirmed;
use App\Notifications\ReservationCancelled;
use App\Notifications\ReservationCompleted;


class ReservationController extends Controller
{

    public function index(Request $request) {
        $reservations = $request->user()
          ->reservations()
            ->with(['offer', 'review'])
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

        $offer->load('user');
        
        $offer->user->notify(
            new ReservationCreated($reservation)
        );

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

        $reservation->offer->user->notify(
            new ReservationCancelled($reservation)
        );


        return response()->json([
            'message' => 'Reservation cancelled successfully.',
            'reservation' => [
                'id' => $reservation->id,
                'status' => $reservation->status,
                'cancelled_at' => $reservation->cancelled_at,
            ],
        ]);
    }

    public function providerIndex(Request $request)
    {
        $reservations = Reservation::query()
            ->whereHas('offer', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->where('type', 'service');
            })
            ->with([
                'offer',
                'user',
            ])
            ->latest('scheduled_at')
            ->paginate(15);

        return ReservationResource::collection($reservations);
    }

    public function providerConfirm(
        Request $request,
        Reservation $reservation
    ) {
        $this->authorize('confirm', $reservation);

        if ($reservation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending reservations can be confirmed.',
            ], 422);
        }

        $reservation->update([
            'status' => 'confirmed',
        ]);

        $reservation->load([
            'offer',
            'user',
        ]);

        $reservation->user->notify(
            new ReservationConfirmed($reservation)
        );

        return new ReservationResource($reservation);
    }

    public function providerCancel(
        Request $request,
        Reservation $reservation
    ) {
        $this->authorize('cancel', $reservation);

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

        $reservation->load([
            'offer',
            'user',
        ]);

        $reservation->user->notify(
            new ReservationCancelled($reservation)
        ); 

        return new ReservationResource($reservation);
    }

    public function providerComplete(
    Request $request,
    Reservation $reservation
    ) {
        $this->authorize('complete', $reservation);

        if ($reservation->status !== 'confirmed') {
            return response()->json([
                'message' => 'Only confirmed reservations can be completed.',
            ], 422);
        }

        $reservation->update([
            'status' => 'completed',
        ]);

        $reservation->load([
            'offer',
            'user',
        ]);

        $reservation->user->notify(
            new ReservationCompleted($reservation)
        );

        return new ReservationResource($reservation);
    }
}