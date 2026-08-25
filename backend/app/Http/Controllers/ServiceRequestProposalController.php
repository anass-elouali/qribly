<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Http\Resources\ServiceRequestProposalResource;
use App\Models\ServiceRequestProposal;
use App\Notifications\ReservationCreated;
use App\Services\AcceptServiceRequestProposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceRequestProposalController extends Controller
{
    public function accept(
        Request $request,
        ServiceRequestProposal $proposal,
        AcceptServiceRequestProposal $acceptProposal,
    ) {
        $reservation = $acceptProposal->handle($proposal, $request->user());
        $reservation->offer->user->notify(new ReservationCreated($reservation));

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }

    public function decline(Request $request, ServiceRequestProposal $proposal)
    {
        $proposal->loadMissing('serviceRequest');
        abort_unless($proposal->serviceRequest->user_id === $request->user()->id, 403);

        $proposal = DB::transaction(function () use ($proposal) {
            $lockedProposal = ServiceRequestProposal::query()
                ->lockForUpdate()
                ->findOrFail($proposal->id);

            if ($lockedProposal->status !== 'pending') {
                abort(422, 'Cette proposition ne peut plus être refusée.');
            }

            $lockedProposal->update(['status' => 'declined']);

            return $lockedProposal;
        });

        $proposal->load(['provider', 'offer']);

        return new ServiceRequestProposalResource($proposal);
    }
}
