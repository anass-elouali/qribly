<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationCancelled;
use App\Notifications\ReservationCompleted;
use App\Notifications\ReservationConfirmed;
use App\Notifications\ReservationCreated;
use App\Services\OfferAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $reservations = $request->user()
            ->reservations()
            ->with(['offer', 'review'])
            ->latest('scheduled_at')
            ->paginate(15);

        return ReservationResource::collection($reservations);
    }

    public function store(
        StoreReservationRequest $request,
        Offer $offer,
        OfferAvailabilityService $availabilityService,
    ) {
        if ($offer->type !== 'service') {
            return response()->json([
                'message' => 'Seules les annonces de service peuvent être réservées.',
            ], 422);
        }

        if ($offer->user_id === $request->user()->id) {
            return response()->json([
                'message' => 'Tu ne peux pas réserver ta propre annonce.',
            ], 403);
        }

        $scheduledAt = $request->date('scheduled_at')->utc();

        $reservation = DB::transaction(function () use (
            $request,
            $offer,
            $scheduledAt,
            $availabilityService,
        ) {
            $lockedOffer = Offer::query()
                ->lockForUpdate()
                ->findOrFail($offer->id);

            User::query()->lockForUpdate()->findOrFail($lockedOffer->user_id);

            if ($lockedOffer->status !== 'active') {
                throw ValidationException::withMessages([
                    'offer' => "Cette annonce n'est pas disponible à la réservation.",
                ]);
            }

            if (! $availabilityService->isSlotAvailable($lockedOffer, $scheduledAt)) {
                throw ValidationException::withMessages([
                    'scheduled_at' => "Ce créneau n'est plus disponible. Choisis une autre heure.",
                ]);
            }

            return $request->user()
                ->reservations()
                ->create([
                    'offer_id' => $lockedOffer->id,
                    'scheduled_at' => $scheduledAt,
                    'duration_minutes' => $lockedOffer->service_duration_minutes ?: 60,
                    'notes' => $request->validated('notes'),
                    'status' => 'pending',
                ]);
        });

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
                'message' => 'Tu ne peux pas annuler cette réservation.',
            ], 403);
        }

        if (! in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Cette réservation ne peut plus être annulée.',
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
            'message' => 'Réservation annulée.',
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
                'message' => 'Seules les réservations en attente peuvent être confirmées.',
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

        if (! in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Cette réservation ne peut plus être annulée.',
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
                'message' => 'Seules les réservations confirmées peuvent être terminées.',
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
