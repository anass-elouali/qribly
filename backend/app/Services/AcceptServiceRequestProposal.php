<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Reservation;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestProposal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptServiceRequestProposal
{
    public function __construct(
        private OfferAvailabilityService $availabilityService,
        private ServiceRequestMatchingService $matchingService,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(ServiceRequestProposal $proposal, User $customer): Reservation
    {
        return DB::transaction(function () use ($proposal, $customer) {
            $serviceRequest = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($proposal->service_request_id);

            $lockedProposal = ServiceRequestProposal::query()
                ->lockForUpdate()
                ->findOrFail($proposal->id);

            if ($serviceRequest->user_id !== $customer->id) {
                throw new AuthorizationException('Tu ne peux pas accepter cette proposition.');
            }

            if (! $serviceRequest->isOpen()) {
                throw ValidationException::withMessages([
                    'service_request' => "Cette demande n'est plus ouverte.",
                ]);
            }

            if ($lockedProposal->status !== 'pending') {
                throw ValidationException::withMessages([
                    'proposal' => "Cette proposition n'est plus disponible.",
                ]);
            }

            $offer = Offer::query()
                ->lockForUpdate()
                ->findOrFail($lockedProposal->offer_id);
            $provider = User::query()
                ->lockForUpdate()
                ->findOrFail($lockedProposal->provider_id);

            if (! $this->matchingService->offerMatches($serviceRequest, $offer, $provider)) {
                throw ValidationException::withMessages([
                    'offer' => "Le service proposé n'est plus compatible avec la demande.",
                ]);
            }

            $scheduledEnd = $lockedProposal->scheduled_at
                ->copy()
                ->addMinutes($offer->service_duration_minutes ?: 60);

            if (
                $lockedProposal->scheduled_at->lessThan($serviceRequest->desired_start_at)
                || $scheduledEnd->greaterThan($serviceRequest->desired_end_at)
            ) {
                throw ValidationException::withMessages([
                    'scheduled_at' => 'Le créneau ne respecte plus la période demandée.',
                ]);
            }

            if (
                $serviceRequest->budget_max !== null
                && (float) $lockedProposal->proposed_price > (float) $serviceRequest->budget_max
            ) {
                throw ValidationException::withMessages([
                    'proposed_price' => 'Le prix dépasse le budget de la demande.',
                ]);
            }

            if (! $this->availabilityService->isSlotAvailable($offer, $lockedProposal->scheduled_at)) {
                throw ValidationException::withMessages([
                    'scheduled_at' => "Ce créneau n'est plus disponible.",
                ]);
            }

            $reservation = Reservation::create([
                'user_id' => $customer->id,
                'offer_id' => $offer->id,
                'service_request_id' => $serviceRequest->id,
                'service_request_proposal_id' => $lockedProposal->id,
                'scheduled_at' => $lockedProposal->scheduled_at,
                'duration_minutes' => $offer->service_duration_minutes ?: 60,
                'agreed_price' => $lockedProposal->proposed_price,
                'notes' => Str::limit(
                    "Demande Qrib #{$serviceRequest->id} : {$serviceRequest->summary}",
                    1000,
                    '',
                ),
                'status' => 'pending',
            ]);

            $lockedProposal->update(['status' => 'accepted']);

            $serviceRequest->proposals()
                ->whereKeyNot($lockedProposal->id)
                ->where('status', 'pending')
                ->update(['status' => 'declined']);

            $serviceRequest->update(['status' => 'fulfilled']);

            return $reservation->load(['offer.user', 'user']);
        });
    }
}
