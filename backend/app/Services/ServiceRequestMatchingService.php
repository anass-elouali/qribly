<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServiceRequestMatchingService
{
    public function __construct(
        private SemanticOfferRankingService $semanticOfferRanking,
    ) {}

    public function eligibleProviders(ServiceRequest $serviceRequest): Collection
    {
        $providerIds = $this->refreshMatches($serviceRequest)
            ->pluck('provider_id')
            ->unique()
            ->values();
        $providers = User::query()
            ->whereIn('id', $providerIds)
            ->get()
            ->keyBy('id');

        return $providerIds
            ->map(fn (int $providerId) => $providers->get($providerId))
            ->filter()
            ->values();
    }

    public function requestsForProvider(User $provider): Builder
    {
        return ServiceRequest::query()
            ->where('user_id', '!=', $provider->id)
            ->where('status', 'open')
            ->where('expires_at', '>', now())
            ->whereHas('matches', function (Builder $query) use ($provider) {
                $query->where('provider_id', $provider->id);
            });
    }

    /**
     * @param  array<int, int>  $previouslyMatchedRequestIds
     * @return Collection<int, ServiceRequest>
     */
    public function refreshProviderMatchesForOffer(
        Offer $offer,
        array $previouslyMatchedRequestIds = [],
    ): Collection {
        $matchedRequestIds = ServiceRequestMatch::query()
            ->where('provider_id', $offer->user_id)
            ->pluck('service_request_id')
            ->map(fn (int $requestId): int => $requestId)
            ->merge($previouslyMatchedRequestIds)
            ->unique()
            ->flip();

        return $this->affectedOpenRequests($offer)
            ->map(function (ServiceRequest $serviceRequest) use ($offer, $matchedRequestIds) {
                $match = $this->refreshProviderMatch(
                    $serviceRequest,
                    $offer->user_id,
                );

                return $match !== null && ! $matchedRequestIds->has($serviceRequest->id)
                    ? $serviceRequest
                    : null;
            })
            ->filter()
            ->values();
    }

    /** @return Collection<int, ServiceRequestMatch> */
    private function refreshMatches(ServiceRequest $serviceRequest): Collection
    {
        $offers = Offer::query()
            ->with(['category', 'user'])
            ->where('user_id', '!=', $serviceRequest->user_id)
            ->where(function (Builder $query) use ($serviceRequest) {
                $this->applyOfferCriteria($query, $serviceRequest);
            })
            ->get();

        if ($offers->isEmpty()) {
            $serviceRequest->matches()->delete();

            return collect();
        }

        $relevantOffers = $this->relevantOffers($serviceRequest, $offers);

        $bestOfferByProvider = $relevantOffers
            ->groupBy('user_id')
            ->map(fn (Collection $providerOffers) => $providerOffers->first())
            ->values();

        return DB::transaction(function () use ($serviceRequest, $bestOfferByProvider) {
            $serviceRequest->matches()->delete();

            return $bestOfferByProvider->map(
                fn (Offer $offer) => $serviceRequest->matches()->create([
                    'provider_id' => $offer->user_id,
                    'offer_id' => $offer->id,
                    'relevance_score' => $offer->getAttribute('semantic_score'),
                ]),
            );
        });
    }

    private function refreshProviderMatch(
        ServiceRequest $serviceRequest,
        int $providerId,
    ): ?ServiceRequestMatch {
        $offers = Offer::query()
            ->with(['category', 'user'])
            ->where('user_id', $providerId)
            ->where(function (Builder $query) use ($serviceRequest) {
                $this->applyOfferCriteria($query, $serviceRequest);
            })
            ->get();

        $bestOffer = $offers->isEmpty()
            ? null
            : $this->relevantOffers($serviceRequest, $offers)->first();

        return DB::transaction(function () use (
            $serviceRequest,
            $providerId,
            $bestOffer,
        ): ?ServiceRequestMatch {
            if ($bestOffer === null) {
                $serviceRequest->matches()
                    ->where('provider_id', $providerId)
                    ->delete();

                return null;
            }

            return $serviceRequest->matches()->updateOrCreate(
                ['provider_id' => $providerId],
                [
                    'offer_id' => $bestOffer->id,
                    'relevance_score' => $bestOffer->getAttribute('semantic_score'),
                ],
            );
        });
    }

    /** @return Collection<int, ServiceRequest> */
    private function affectedOpenRequests(Offer $offer): Collection
    {
        return ServiceRequest::query()
            ->where('user_id', '!=', $offer->user_id)
            ->where('status', 'open')
            ->where('expires_at', '>', now())
            ->where(function (Builder $query) use ($offer) {
                $query->whereHas('matches', function (Builder $matchQuery) use ($offer) {
                    $matchQuery->where('provider_id', $offer->user_id);
                });

                if ($this->offerCanMatchRequests($offer)) {
                    $query->orWhere(function (Builder $requestQuery) use ($offer) {
                        $requestQuery
                            ->where('category_id', $offer->category_id)
                            ->whereRaw('LOWER(city) = LOWER(?)', [$offer->city]);

                        if ($offer->at_customer_location && ! $offer->at_provider_location) {
                            $requestQuery->where('at_home', true);
                        } elseif (! $offer->at_customer_location && $offer->at_provider_location) {
                            $requestQuery->where('at_home', false);
                        }
                    });
                }
            })
            ->get();
    }

    /**
     * @param  Collection<int, Offer>  $offers
     * @return Collection<int, Offer>
     */
    private function relevantOffers(
        ServiceRequest $serviceRequest,
        Collection $offers,
    ): Collection {
        $rankedOffers = $this->semanticOfferRanking->rank(
            $serviceRequest->summary,
            $offers,
        );

        if ($rankedOffers === null) {
            return $offers;
        }

        $threshold = (float) config(
            'services.ai.service_request_match_threshold',
            0.50,
        );

        return $rankedOffers->filter(
            fn (Offer $offer): bool => (float) $offer->getAttribute('semantic_score') >= $threshold,
        );
    }

    private function offerCanMatchRequests(Offer $offer): bool
    {
        return $offer->type === 'service'
            && $offer->status === 'active'
            && ($offer->at_customer_location || $offer->at_provider_location);
    }

    public function offerMatches(
        ServiceRequest $serviceRequest,
        Offer $offer,
        User $provider,
    ): bool {
        $isStructurallyCompatible = $offer->user_id === $provider->id
            && $offer->type === 'service'
            && $offer->status === 'active'
            && $offer->category_id === $serviceRequest->category_id
            && mb_strtolower((string) $offer->city) === mb_strtolower($serviceRequest->city)
            && $this->locationModeMatches($serviceRequest, $offer);

        if (! $isStructurallyCompatible) {
            return false;
        }

        return $serviceRequest->matches()
            ->where('provider_id', $provider->id)
            ->where('offer_id', $offer->id)
            ->exists();
    }

    private function applyOfferCriteria(Builder $query, ServiceRequest $serviceRequest): void
    {
        $query
            ->where('type', 'service')
            ->where('status', 'active')
            ->where('category_id', $serviceRequest->category_id)
            ->whereRaw('LOWER(city) = LOWER(?)', [$serviceRequest->city])
            ->where(
                $serviceRequest->at_home
                    ? 'at_customer_location'
                    : 'at_provider_location',
                true,
            );
    }

    private function locationModeMatches(
        ServiceRequest $serviceRequest,
        Offer $offer,
    ): bool {
        return $serviceRequest->at_home
            ? $offer->at_customer_location
            : $offer->at_provider_location;
    }
}
