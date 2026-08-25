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

        $rankedOffers = $this->semanticOfferRanking->rank(
            $serviceRequest->summary,
            $offers,
        );

        if ($rankedOffers === null) {
            $relevantOffers = $offers;
        } else {
            $threshold = (float) config(
                'services.ai.service_request_match_threshold',
                0.60,
            );
            $relevantOffers = $rankedOffers->filter(
                fn (Offer $offer): bool => (float) $offer->getAttribute('semantic_score') >= $threshold,
            );
        }

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
