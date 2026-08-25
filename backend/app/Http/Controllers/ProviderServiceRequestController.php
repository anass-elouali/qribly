<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequestProposalRequest;
use App\Http\Resources\ServiceRequestProposalResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestProposal;
use App\Notifications\ServiceRequestProposalReceived;
use App\Services\OfferAvailabilityService;
use App\Services\ServiceRequestMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProviderServiceRequestController extends Controller
{
    public function index(
        Request $request,
        ServiceRequestMatchingService $matchingService,
    ) {
        $provider = $request->user();
        $serviceRequests = $matchingService
            ->requestsForProvider($provider)
            ->with([
                'category',
                'user',
                'proposals' => function ($query) use ($provider) {
                    $query
                        ->where('provider_id', $provider->id)
                        ->with(['provider', 'offer']);
                },
            ])
            ->latest()
            ->paginate(10);

        return ServiceRequestResource::collection($serviceRequests);
    }

    public function upsertProposal(
        StoreServiceRequestProposalRequest $request,
        ServiceRequest $serviceRequest,
        ServiceRequestMatchingService $matchingService,
        OfferAvailabilityService $availabilityService,
    ) {
        $provider = $request->user();
        $offer = Offer::query()->findOrFail($request->integer('offer_id'));

        if ($offer->user_id !== $provider->id) {
            abort(403, "Tu ne peux proposer que l'une de tes annonces.");
        }

        if (! $matchingService->offerMatches($serviceRequest, $offer, $provider)) {
            throw ValidationException::withMessages([
                'offer_id' => "Cette annonce n'est pas compatible avec la demande.",
            ]);
        }

        $scheduledAt = $request->date('scheduled_at')->utc();
        $scheduledEnd = $scheduledAt->copy()
            ->addMinutes($offer->service_duration_minutes ?: 60);

        if (
            $scheduledAt->lessThan($serviceRequest->desired_start_at)
            || $scheduledEnd->greaterThan($serviceRequest->desired_end_at)
        ) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Le créneau doit respecter la période demandée.',
            ]);
        }

        if (
            $serviceRequest->budget_max !== null
            && $request->float('proposed_price') > (float) $serviceRequest->budget_max
        ) {
            throw ValidationException::withMessages([
                'proposed_price' => 'Le prix proposé dépasse le budget du client.',
            ]);
        }

        if (! $availabilityService->isSlotAvailable($offer, $scheduledAt)) {
            throw ValidationException::withMessages([
                'scheduled_at' => "Ce créneau n'est pas disponible.",
            ]);
        }

        [$proposal, $created] = DB::transaction(function () use (
            $serviceRequest,
            $provider,
            $offer,
            $request,
            $scheduledAt,
        ) {
            $lockedRequest = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($serviceRequest->id);

            if (! $lockedRequest->isOpen()) {
                throw ValidationException::withMessages([
                    'service_request' => "Cette demande n'est plus ouverte.",
                ]);
            }

            $proposal = ServiceRequestProposal::query()
                ->where('service_request_id', $lockedRequest->id)
                ->where('provider_id', $provider->id)
                ->lockForUpdate()
                ->first();

            if ($proposal?->status === 'accepted') {
                throw ValidationException::withMessages([
                    'proposal' => 'Une proposition déjà acceptée ne peut pas être modifiée.',
                ]);
            }

            $created = $proposal === null;
            $proposal ??= new ServiceRequestProposal([
                'provider_id' => $provider->id,
            ]);

            $proposal->fill([
                'offer_id' => $offer->id,
                'proposed_price' => $request->input('proposed_price'),
                'scheduled_at' => $scheduledAt,
                'message' => $request->input('message'),
                'status' => 'pending',
            ]);

            $lockedRequest->proposals()->save($proposal);

            return [$proposal, $created];
        });

        $proposal->load(['provider', 'offer', 'serviceRequest.user']);
        $proposal->serviceRequest->user->notify(
            new ServiceRequestProposalReceived($proposal),
        );

        return (new ServiceRequestProposalResource($proposal))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    public function withdrawProposal(
        Request $request,
        ServiceRequestProposal $proposal,
    ) {
        abort_unless($proposal->provider_id === $request->user()->id, 403);

        $proposal = DB::transaction(function () use ($proposal) {
            $lockedProposal = ServiceRequestProposal::query()
                ->lockForUpdate()
                ->findOrFail($proposal->id);

            if ($lockedProposal->status !== 'pending') {
                abort(422, 'Cette proposition ne peut plus être retirée.');
            }

            $lockedProposal->update(['status' => 'withdrawn']);

            return $lockedProposal;
        });

        $proposal->load(['provider', 'offer']);

        return new ServiceRequestProposalResource($proposal);
    }
}
