<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfferTest extends TestCase
{
    use RefreshDatabase;

    private function createOffer(
        User $provider,
        float $latitude,
        float $longitude,
        string $title,
        string $city = 'Marrakech',
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
            'city' => $city,
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

        $response = $this->getJson('/api/offers/nearby?'.http_build_query([
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

    public function test_nearby_offers_can_include_ourika_after_the_first_ten_results(): void
    {
        $provider = User::factory()->create();

        foreach (range(1, 10) as $index) {
            $this->createOffer(
                $provider,
                31.6295 + ($index * 0.001),
                -7.9811,
                "Service proche {$index}",
            );
        }

        $ourikaOffer = $this->createOffer(
            $provider,
            31.3742,
            -7.7778,
            'Location de VTT à Ourika',
            'Ourika',
        );

        $response = $this->getJson('/api/offers/nearby?'.http_build_query([
            'latitude' => 31.6295,
            'longitude' => -7.9811,
            'radius' => 50,
            'per_page' => 100,
        ]));

        $response
            ->assertSuccessful()
            ->assertJsonCount(11, 'data')
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonFragment([
                'id' => $ourikaOffer->id,
                'city' => 'Ourika',
            ]);

        $ourikaResult = collect($response->json('data'))->firstWhere('id', $ourikaOffer->id);

        $this->assertNotNull($ourikaResult);
        $this->assertLessThan(50_000, $ourikaResult['distance']);
    }

    public function test_offers_can_be_filtered_by_city(): void
    {
        $provider = User::factory()->create();
        $marrakechOffer = $this->createOffer(
            $provider,
            31.6295,
            -7.9811,
            'Service à Marrakech',
            'Marrakech',
        );
        $casablancaOffer = $this->createOffer(
            $provider,
            33.5731,
            -7.5898,
            'Service à Casablanca',
            'Casablanca',
        );

        $response = $this->getJson('/api/offers?city=Marrakech');

        $response
            ->assertSuccessful()
            ->assertJsonFragment([
                'id' => $marrakechOffer->id,
                'city' => 'Marrakech',
            ])
            ->assertJsonMissing([
                'id' => $casablancaOffer->id,
            ]);
    }

    public function test_offer_city_is_inferred_from_coordinates_when_omitted(): void
    {
        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);

        $this
            ->actingAs($provider)
            ->postJson('/api/offers', [
                'category_id' => $category->id,
                'title' => 'Nettoyage test',
                'description' => 'Une description assez complète pour le service de test.',
                'type' => 'service',
                'service_duration_minutes' => 60,
                'price' => 200,
                'is_negotiable' => false,
                'status' => 'active',
                'location' => [
                    'latitude' => 31.6295,
                    'longitude' => -7.9811,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.city', 'Marrakech');

        $this->assertDatabaseHas('offers', [
            'title' => 'Nettoyage test',
            'city' => 'Marrakech',
        ]);
    }

    public function test_offer_pagination_does_not_repeat_offers_with_the_same_creation_date(): void
    {
        $provider = User::factory()->create();

        foreach (range(1, 12) as $index) {
            $this->createOffer(
                $provider,
                31.6295,
                -7.9811,
                "Service {$index}",
            );
        }

        DB::table('offers')->update([
            'created_at' => '2026-08-23 10:00:00',
        ]);

        $firstPageIds = $this->getJson('/api/offers?page=1')
            ->assertSuccessful()
            ->json('data.*.id');
        $secondPageIds = $this->getJson('/api/offers?page=2')
            ->assertSuccessful()
            ->json('data.*.id');

        $this->assertCount(12, array_unique([...$firstPageIds, ...$secondPageIds]));
        $this->assertSame([], array_values(array_intersect($firstPageIds, $secondPageIds)));

        $this->getJson('/api/offers?per_page=100')
            ->assertSuccessful()
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.last_page', 1);
    }

    public function test_offer_can_be_created_with_an_avif_image(): void
    {
        Storage::fake('public');

        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Électronique']);
        $avifHeader = hex2bin(
            '00000020667479706176696600000000617669666d6966316d6961664d413142'
        );

        $response = $this
            ->actingAs($provider)
            ->post('/api/offers', [
                'category_id' => $category->id,
                'title' => 'Produit avec photo AVIF',
                'description' => 'Une annonce de test avec une photo au format AVIF.',
                'type' => 'product',
                'price' => 100,
                'is_negotiable' => false,
                'status' => 'active',
                'city' => 'Fès',
                'location' => [
                    'latitude' => 34.017238,
                    'longitude' => -5.013564,
                ],
                'images' => [
                    UploadedFile::fake()->createWithContent('annonce.avif', $avifHeader),
                ],
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertCreated()
            ->assertJsonCount(1, 'data.images');

        $storedPath = DB::table('offer_images')->value('path');

        $this->assertNotNull($storedPath);
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_offer_rejects_a_non_image_file_with_a_french_message(): void
    {
        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Électronique']);

        $response = $this
            ->actingAs($provider)
            ->post('/api/offers', [
                'category_id' => $category->id,
                'title' => 'Produit avec faux fichier',
                'description' => 'Cette annonce de test doit être refusée par la validation.',
                'type' => 'product',
                'price' => 100,
                'is_negotiable' => false,
                'status' => 'active',
                'city' => 'Fès',
                'location' => [
                    'latitude' => 34.017238,
                    'longitude' => -5.013564,
                ],
                'images' => [
                    UploadedFile::fake()->createWithContent('faux.jpg', 'Ceci n’est pas une image.'),
                ],
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.images.0.0',
                'Chaque photo doit être au format JPG, PNG, WEBP ou AVIF.'
            );
    }
}
