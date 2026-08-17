<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfferTest extends TestCase
{
    use RefreshDatabase;

    private function createOffer(
        User $provider,
        float $latitude,
        float $longitude,
        string $title
    ): Offer {
        $category = Category::firstOrCreate([
            'name' => 'Test Category',
        ]);

        $offer = new Offer([
            'category_id' => $category->id,
            'title' => $title,
            'description' => 'Test service description',
            'type' => 'service',
            'price' => 100,
            'is_negotiable' => false,
            'status' => 'active',
        ]);

        $offer->user()->associate($provider);
        $offer->save();

        DB::statement(
            'UPDATE offers
             SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
             WHERE id = ?',
            [$longitude, $latitude, $offer->id]
        );

        return $offer;
    }

    public function test_nearby_offers_returns_offers_within_radius(): void
    {
        $provider = User::factory()->create();

        $nearbyOffer = $this->createOffer(
            $provider,
            31.9539,
            -6.5714,
            'Nearby Service'
        );

        $farOffer = $this->createOffer(
            $provider,
            33.5731,
            -7.5898,
            'Far Away Service'
        );

        $response = $this->getJson('/api/offers/nearby?' . http_build_query([
            'latitude' => 31.9539,
            'longitude' => -6.5714,
            'radius' => 10,
        ]));

        $response->assertSuccessful();

        $response->assertJsonFragment([
            'id' => $nearbyOffer->id,
        ]);

        $response->assertJsonMissing([
            'id' => $farOffer->id,
        ]);
    }
}