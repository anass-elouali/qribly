<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ServiceRequestMatchingService
{
    public function eligibleProviders(ServiceRequest $serviceRequest): Collection
    {
        return User::query()
            ->where('id', '!=', $serviceRequest->user_id)
            ->whereHas('offers', function (Builder $query) use ($serviceRequest) {
                $this->applyOfferCriteria($query, $serviceRequest);
            })
            ->get();
    }

    public function requestsForProvider(User $provider): Builder
    {
        return ServiceRequest::query()
            ->where('user_id', '!=', $provider->id)
            ->where('status', 'open')
            ->where('expires_at', '>', now())
            ->whereExists(function ($query) use ($provider) {
                $query->selectRaw('1')
                    ->from('offers')
                    ->where('offers.user_id', $provider->id)
                    ->where('offers.type', 'service')
                    ->where('offers.status', 'active')
                    ->whereColumn('offers.category_id', 'service_requests.category_id')
                    ->whereRaw('LOWER(offers.city) = LOWER(service_requests.city)');
            });
    }

    public function offerMatches(
        ServiceRequest $serviceRequest,
        Offer $offer,
        User $provider,
    ): bool {
        return $offer->user_id === $provider->id
            && $offer->type === 'service'
            && $offer->status === 'active'
            && $offer->category_id === $serviceRequest->category_id
            && mb_strtolower((string) $offer->city) === mb_strtolower($serviceRequest->city);
    }

    private function applyOfferCriteria(Builder $query, ServiceRequest $serviceRequest): void
    {
        $query
            ->where('type', 'service')
            ->where('status', 'active')
            ->where('category_id', $serviceRequest->category_id)
            ->whereRaw('LOWER(city) = LOWER(?)', [$serviceRequest->city]);
    }
}
