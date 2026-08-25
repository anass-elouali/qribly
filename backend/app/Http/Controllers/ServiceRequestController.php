<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequestRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Notifications\ServiceRequestPublished;
use App\Services\ServiceRequestMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        $serviceRequests = $request->user()
            ->serviceRequests()
            ->with([
                'category',
                'user',
                'proposals.provider',
                'proposals.offer',
            ])
            ->withCount('proposals')
            ->latest()
            ->paginate(10);

        return ServiceRequestResource::collection($serviceRequests);
    }

    public function store(
        StoreServiceRequestRequest $request,
        ServiceRequestMatchingService $matchingService,
    ) {
        $data = $request->validated();
        $location = $data['location'] ?? null;
        unset($data['location']);

        $data['desired_start_at'] = $request->date('desired_start_at')->utc();
        $data['desired_end_at'] = $request->date('desired_end_at')->utc();
        $expiryLimit = now()->addDays(7);
        $data['expires_at'] = $data['desired_end_at']->lessThan($expiryLimit)
            ? $data['desired_end_at']
            : $expiryLimit;

        if ($location) {
            $data['location'] = DB::selectOne(
                'SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography AS location',
                [$location['longitude'], $location['latitude']],
            )->location;
        }

        $serviceRequest = $request->user()
            ->serviceRequests()
            ->create($data);

        $serviceRequest->load(['category', 'user']);
        $providers = $matchingService->eligibleProviders($serviceRequest);

        if ($providers->isNotEmpty()) {
            Notification::send($providers, new ServiceRequestPublished($serviceRequest));
        }

        return (new ServiceRequestResource($serviceRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($serviceRequest->user_id === $request->user()->id, 403);

        $serviceRequest->load([
            'category',
            'user',
            'proposals.provider',
            'proposals.offer',
        ])->loadCount('proposals');

        return new ServiceRequestResource($serviceRequest);
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($serviceRequest->user_id === $request->user()->id, 403);

        $serviceRequest = DB::transaction(function () use ($serviceRequest) {
            $lockedRequest = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($serviceRequest->id);

            if (! $lockedRequest->isOpen()) {
                abort(422, 'Cette demande ne peut plus être annulée.');
            }

            $lockedRequest->update(['status' => 'cancelled']);
            $lockedRequest->proposals()
                ->where('status', 'pending')
                ->update(['status' => 'declined']);

            return $lockedRequest;
        });

        $serviceRequest->load(['category', 'user', 'proposals.provider', 'proposals.offer']);

        return new ServiceRequestResource($serviceRequest);
    }
}
