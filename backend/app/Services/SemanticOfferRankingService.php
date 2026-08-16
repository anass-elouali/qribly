<?php

namespace App\Services;

use App\Models\Offer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SemanticOfferRankingService
{
    /**
     * Ask the AI service to rank nearby offers. A null value means that the
     * AI service is unavailable, so the caller can safely keep distance order.
     *
     * @param Collection<int, Offer> $offers
     * @return Collection<int, Offer>|null
     */
    public function rank(string $query, Collection $offers): ?Collection
    {
        if ($offers->isEmpty()) {
            return $offers;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(config('services.ai.timeout'))
                ->post(config('services.ai.url').'/rank', [
                    'query' => $query,
                    'offers' => $offers->map(fn (Offer $offer) => [
                        'id' => $offer->id,
                        'text' => $this->offerText($offer),
                    ])->values()->all(),
                    'limit' => $offers->count(),
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Semantic search service is unavailable.', [
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Semantic search service returned an error.', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $offerIds = $offers->pluck('id')->flip();
        $scores = collect($response->json('results', []))
            ->filter(fn (mixed $result) => is_array($result)
                && isset($result['id'], $result['semantic_score'])
                && is_numeric($result['semantic_score'])
                && $offerIds->has($result['id']))
            ->mapWithKeys(fn (array $result) => [
                (int) $result['id'] => (float) $result['semantic_score'],
            ]);

        if ($scores->isEmpty()) {
            Log::warning('Semantic search service returned no usable rankings.');

            return null;
        }

        return $offers
            ->sort(function (Offer $left, Offer $right) use ($scores): int {
                $scoreComparison = $scores->get($right->id, -INF)
                    <=> $scores->get($left->id, -INF);

                return $scoreComparison !== 0
                    ? $scoreComparison
                    : $left->distance <=> $right->distance;
            })
            ->values()
            ->each(function (Offer $offer) use ($scores): void {
                if ($scores->has($offer->id)) {
                    $offer->setAttribute('semantic_score', $scores->get($offer->id));
                }
            });
    }

    private function offerText(Offer $offer): string
    {
        $parts = [
            $offer->title,
            $offer->description,
            $offer->category?->name ? "Catégorie : {$offer->category->name}" : null,
            "Type : {$offer->type}",
        ];

        return implode('. ', array_filter($parts));
    }
}
