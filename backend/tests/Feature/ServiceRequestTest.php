<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestMatch;
use App\Models\ServiceRequestProposal;
use App\Models\User;
use App\Notifications\ReservationCreated;
use App\Notifications\ServiceRequestProposalReceived;
use App\Notifications\ServiceRequestPublished;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-08-24 06:00:00', 'UTC'),
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function createService(
        User $provider,
        Category $category,
        string $city = 'Marrakech',
        int $duration = 60,
        string $title = 'Service à domicile',
        string $description = 'Description complète du service proposé.',
        bool $atCustomerLocation = true,
        bool $atProviderLocation = true,
    ): Offer {
        $offer = new Offer([
            'category_id' => $category->id,
            'title' => $title,
            'description' => $description,
            'type' => 'service',
            'price' => 250,
            'is_negotiable' => true,
            'status' => 'active',
            'city' => $city,
            'service_duration_minutes' => $duration,
            'at_customer_location' => $atCustomerLocation,
            'at_provider_location' => $atProviderLocation,
        ]);

        $offer->user()->associate($provider);
        $offer->save();

        return $offer;
    }

    private function createServiceRequest(
        User $customer,
        Category $category,
        array $overrides = [],
    ): ServiceRequest {
        return $customer->serviceRequests()->create(array_merge([
            'category_id' => $category->id,
            'raw_text' => 'Je cherche une intervention à domicile à Marrakech.',
            'summary' => 'Intervention à domicile à Marrakech.',
            'city' => 'Marrakech',
            'desired_start_at' => CarbonImmutable::parse('2026-08-25 09:00:00', 'UTC'),
            'desired_end_at' => CarbonImmutable::parse('2026-08-25 18:00:00', 'UTC'),
            'budget_max' => 300,
            'at_home' => true,
            'status' => 'open',
            'expires_at' => CarbonImmutable::parse('2026-08-25 18:00:00', 'UTC'),
        ], $overrides));
    }

    private function createProposal(
        ServiceRequest $serviceRequest,
        User $provider,
        Offer $offer,
        string $scheduledAt = '2026-08-25 10:00:00',
        int $price = 250,
    ): ServiceRequestProposal {
        $this->createMatch($serviceRequest, $provider, $offer);

        return $serviceRequest->proposals()->create([
            'provider_id' => $provider->id,
            'offer_id' => $offer->id,
            'proposed_price' => $price,
            'scheduled_at' => CarbonImmutable::parse($scheduledAt, 'UTC'),
            'message' => 'Je suis disponible pour cette intervention.',
            'status' => 'pending',
        ]);
    }

    private function createMatch(
        ServiceRequest $serviceRequest,
        User $provider,
        Offer $offer,
        float $score = 0.90,
    ): ServiceRequestMatch {
        return $serviceRequest->matches()->updateOrCreate(
            ['provider_id' => $provider->id],
            [
                'offer_id' => $offer->id,
                'relevance_score' => $score,
            ],
        );
    }

    private function updateOfferPayload(Offer $offer, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $offer->category_id,
            'title' => $offer->title,
            'description' => $offer->description,
            'type' => $offer->type,
            'service_duration_minutes' => $offer->service_duration_minutes,
            'at_customer_location' => $offer->at_customer_location,
            'at_provider_location' => $offer->at_provider_location,
            'price' => $offer->price,
            'is_negotiable' => (bool) $offer->is_negotiable,
            'status' => $offer->status,
            'city' => $offer->city,
        ], $overrides);
    }

    public function test_authentication_is_required_to_publish_a_service_request(): void
    {
        $this->postJson('/api/service-requests', [])->assertUnauthorized();
    }

    public function test_customer_can_publish_a_request_and_compatible_providers_are_notified(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $compatibleProvider = User::factory()->create();
        $irrelevantProvider = User::factory()->create();
        $wrongLocationProvider = User::factory()->create();
        $otherProvider = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);

        $compatibleOffer = $this->createService(
            $compatibleProvider,
            $category,
            title: 'Dépannage plomberie et fuite d’eau',
            description: 'Réparation de robinet, siphon et canalisation à domicile.',
        );
        $irrelevantOffer = $this->createService(
            $irrelevantProvider,
            $category,
            title: 'Nettoyage complet de maison',
            description: 'Ménage, vitres, sols et dépoussiérage à domicile.',
        );
        $wrongLocationOffer = $this->createService(
            $wrongLocationProvider,
            $category,
            title: 'Dépannage plomberie en atelier',
            description: 'Réparation de robinet et de matériel dans notre atelier.',
            atCustomerLocation: false,
            atProviderLocation: true,
        );
        $this->createService(
            $otherProvider,
            $category,
            'Rabat',
            title: 'Dépannage plomberie à Rabat',
        );

        Http::fake([
            '*/rank' => Http::response([
                'results' => [
                    [
                        'id' => $compatibleOffer->id,
                        'semantic_score' => 0.91,
                    ],
                    [
                        // Measured against the real ranking service (2026-08-25):
                        // a cleaning offer scores higher than expected against a
                        // "fix a leak" query, because both texts share a lot of
                        // generic "à domicile" vocabulary from the same category.
                        // This is exactly the kind of same-category noise the
                        // threshold has to reject without also rejecting genuine
                        // matches phrased differently (see the house-cleaning and
                        // arabic-teacher tests below) — the real value is used
                        // here on purpose, not a round number picked to pass.
                        'id' => $irrelevantOffer->id,
                        'semantic_score' => 0.3789,
                    ],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($customer)
            ->postJson('/api/service-requests', [
                'raw_text' => 'Mon robinet fuit, je cherche une intervention à Marrakech.',
                'summary' => 'Réparer une fuite à domicile à Marrakech.',
                'category_id' => $category->id,
                'city' => 'Marrakech',
                'desired_start_at' => '2026-08-25T09:00:00Z',
                'desired_end_at' => '2026-08-25T18:00:00Z',
                'budget_max' => 300,
                'at_home' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.city', 'Marrakech')
            ->assertJsonPath('data.budget_max', '300.00');

        $this->assertDatabaseHas('service_requests', [
            'user_id' => $customer->id,
            'category_id' => $category->id,
            'city' => 'Marrakech',
            'status' => 'open',
        ]);
        $serviceRequestId = $response->json('data.id');
        $this->assertDatabaseHas('service_request_matches', [
            'service_request_id' => $serviceRequestId,
            'provider_id' => $compatibleProvider->id,
            'offer_id' => $compatibleOffer->id,
            'relevance_score' => 0.91,
        ]);
        $this->assertDatabaseMissing('service_request_matches', [
            'service_request_id' => $serviceRequestId,
            'provider_id' => $irrelevantProvider->id,
        ]);
        $this->assertDatabaseMissing('service_request_matches', [
            'service_request_id' => $serviceRequestId,
            'provider_id' => $wrongLocationProvider->id,
        ]);

        Http::assertSent(function (HttpRequest $request) use (
            $compatibleOffer,
            $irrelevantOffer,
            $wrongLocationOffer,
        ): bool {
            $offerIds = collect($request['offers'])->pluck('id');

            return $offerIds->contains($compatibleOffer->id)
                && $offerIds->contains($irrelevantOffer->id)
                && ! $offerIds->contains($wrongLocationOffer->id);
        });

        Notification::assertSentTo($compatibleProvider, ServiceRequestPublished::class);
        Notification::assertNotSentTo($irrelevantProvider, ServiceRequestPublished::class);
        Notification::assertNotSentTo($wrongLocationProvider, ServiceRequestPublished::class);
        Notification::assertNotSentTo($otherProvider, ServiceRequestPublished::class);
    }

    public function test_arabic_teacher_is_kept_above_the_adjusted_threshold_while_other_courses_are_excluded(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $arabicProvider = User::factory()->create();
        $mathProvider = User::factory()->create();
        $englishProvider = User::factory()->create();
        $category = Category::create(['name' => 'Éducation']);

        $arabicOffer = $this->createService(
            $arabicProvider,
            $category,
            city: 'Essaouira',
            title: 'Prof arabe',
            description: 'Cours particuliers de langue arabe à domicile : lecture, écriture et conversation.',
        );
        $mathOffer = $this->createService(
            $mathProvider,
            $category,
            city: 'Essaouira',
            title: 'Cours de soutien en mathématiques',
            description: 'Cours de mathématiques pour le collège et le lycée.',
        );
        $englishOffer = $this->createService(
            $englishProvider,
            $category,
            city: 'Essaouira',
            title: 'Cours d’anglais conversationnel',
            description: 'Cours d’anglais axé sur le vocabulaire et la conversation.',
        );

        Http::fake([
            '*/rank' => Http::response([
                'results' => [
                    ['id' => $arabicOffer->id, 'semantic_score' => 0.5515],
                    ['id' => $mathOffer->id, 'semantic_score' => 0.1801],
                    ['id' => $englishOffer->id, 'semantic_score' => 0.175],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($customer)
            ->postJson('/api/service-requests', [
                'raw_text' => 'Je cherche un professeur d’arabe à Essaouira le 28 août 2026 entre 14 h et 18 h, à mon domicile. Budget 200 DH.',
                'summary' => 'Recherche d’un professeur d’arabe à domicile à Essaouira le 28 août 2026, 14:00-18:00, budget 200 DH.',
                'category_id' => $category->id,
                'city' => 'Essaouira',
                'desired_start_at' => '2026-08-28T13:00:00Z',
                'desired_end_at' => '2026-08-28T17:00:00Z',
                'budget_max' => 200,
                'at_home' => true,
            ])
            ->assertCreated();

        $serviceRequestId = $response->json('data.id');

        $this->assertDatabaseHas('service_request_matches', [
            'service_request_id' => $serviceRequestId,
            'provider_id' => $arabicProvider->id,
            'offer_id' => $arabicOffer->id,
            'relevance_score' => 0.5515,
        ]);
        $this->assertDatabaseMissing('service_request_matches', [
            'service_request_id' => $serviceRequestId,
            'provider_id' => $mathProvider->id,
        ]);
        $this->assertDatabaseMissing('service_request_matches', [
            'service_request_id' => $serviceRequestId,
            'provider_id' => $englishProvider->id,
        ]);

        Notification::assertSentTo($arabicProvider, ServiceRequestPublished::class);
        Notification::assertNotSentTo($mathProvider, ServiceRequestPublished::class);
        Notification::assertNotSentTo($englishProvider, ServiceRequestPublished::class);
    }

    public function test_customer_only_sees_their_own_requests(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $visible = $this->createServiceRequest($customer, $category);
        $this->createServiceRequest($otherCustomer, $category);

        $this
            ->actingAs($customer)
            ->getJson('/api/service-requests')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_customer_travel_request_only_matches_services_at_provider_location(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $workshopProvider = User::factory()->create();
        $mobileProvider = User::factory()->create();
        $category = Category::create(['name' => 'Transport']);

        $workshopOffer = $this->createService(
            $workshopProvider,
            $category,
            title: 'Réparation de vélo en atelier',
            description: 'Réparation des freins, pneus et vitesses dans notre atelier.',
            atCustomerLocation: false,
            atProviderLocation: true,
        );
        $mobileOffer = $this->createService(
            $mobileProvider,
            $category,
            title: 'Réparation mobile de vélo',
            description: 'Le réparateur intervient directement chez le client.',
            atCustomerLocation: true,
            atProviderLocation: false,
        );

        Http::fake([
            '*/rank' => Http::response([
                'results' => [[
                    'id' => $workshopOffer->id,
                    'semantic_score' => 0.92,
                ]],
            ]),
        ]);

        $response = $this
            ->actingAs($customer)
            ->postJson('/api/service-requests', [
                'raw_text' => 'Je cherche un atelier pour réparer mon vélo à Marrakech.',
                'summary' => 'Réparer mon vélo dans un atelier à Marrakech.',
                'category_id' => $category->id,
                'city' => 'Marrakech',
                'desired_start_at' => '2026-08-25T09:00:00Z',
                'desired_end_at' => '2026-08-25T18:00:00Z',
                'budget_max' => 300,
                'at_home' => false,
            ])
            ->assertCreated();

        $serviceRequestId = $response->json('data.id');
        $this->assertDatabaseHas('service_request_matches', [
            'service_request_id' => $serviceRequestId,
            'provider_id' => $workshopProvider->id,
            'offer_id' => $workshopOffer->id,
        ]);
        $this->assertDatabaseMissing('service_request_matches', [
            'service_request_id' => $serviceRequestId,
            'provider_id' => $mobileProvider->id,
        ]);

        Http::assertSent(function (HttpRequest $request) use (
            $workshopOffer,
            $mobileOffer,
        ): bool {
            $offerIds = collect($request['offers'])->pluck('id');

            return $offerIds->contains($workshopOffer->id)
                && ! $offerIds->contains($mobileOffer->id);
        });

        Notification::assertSentTo($workshopProvider, ServiceRequestPublished::class);
        Notification::assertNotSentTo($mobileProvider, ServiceRequestPublished::class);
    }

    public function test_request_rejects_an_unsupported_city_and_invalid_period(): void
    {
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services']);

        $this
            ->actingAs($customer)
            ->postJson('/api/service-requests', [
                'raw_text' => 'Je cherche une intervention à domicile rapidement.',
                'summary' => 'Intervention à domicile.',
                'category_id' => $category->id,
                'city' => 'Ville inconnue',
                'desired_start_at' => '2026-08-25T18:00:00Z',
                'desired_end_at' => '2026-08-25T09:00:00Z',
                'at_home' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['city', 'desired_end_at']);

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_provider_only_sees_open_compatible_requests(): void
    {
        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        $otherCategory = Category::create(['name' => 'Éducation']);
        $offer = $this->createService($provider, $category);

        $compatible = $this->createServiceRequest($customer, $category);
        $compatible->matches()->create([
            'provider_id' => $provider->id,
            'offer_id' => $offer->id,
            'relevance_score' => 0.91,
        ]);
        $this->createServiceRequest($customer, $category, ['city' => 'Rabat']);
        $this->createServiceRequest($customer, $otherCategory);
        $this->createServiceRequest($customer, $category, ['status' => 'cancelled']);

        $this
            ->actingAs($provider)
            ->getJson('/api/provider/service-requests')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $compatible->id)
            ->assertJsonMissingPath('data.0.raw_text');
    }

    public function test_updating_an_offer_can_create_a_match_for_an_existing_request(): void
    {
        Notification::fake();

        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        $offer = $this->createService(
            $provider,
            $category,
            city: 'Rabat',
            title: 'Dépannage plomberie',
            description: 'Réparation de fuites, robinets et canalisations.',
        );
        $serviceRequest = $this->createServiceRequest($customer, $category);

        Http::fake([
            '*/rank' => Http::response([
                'results' => [[
                    'id' => $offer->id,
                    'semantic_score' => 0.93,
                ]],
            ]),
        ]);

        $this
            ->actingAs($provider)
            ->putJson(
                "/api/offers/{$offer->id}",
                $this->updateOfferPayload($offer, ['city' => 'Marrakech']),
            )
            ->assertSuccessful();

        $this->assertDatabaseHas('service_request_matches', [
            'service_request_id' => $serviceRequest->id,
            'provider_id' => $provider->id,
            'offer_id' => $offer->id,
            'relevance_score' => 0.93,
        ]);

        $offer->refresh();

        $this
            ->actingAs($provider)
            ->putJson(
                "/api/offers/{$offer->id}",
                $this->updateOfferPayload($offer, [
                    'description' => 'Réparation rapide de fuites, robinets et canalisations.',
                ]),
            )
            ->assertSuccessful();

        Notification::assertSentToTimes(
            $provider,
            ServiceRequestPublished::class,
            1,
        );
    }

    public function test_updating_an_offer_can_replace_the_provider_match(): void
    {
        Notification::fake();

        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        $oldOffer = $this->createService(
            $provider,
            $category,
            title: 'Dépannage général',
        );
        $updatedOffer = $this->createService(
            $provider,
            $category,
            title: 'Service général',
        );
        $serviceRequest = $this->createServiceRequest($customer, $category, [
            'summary' => 'Réparer une fuite de plomberie à domicile à Marrakech.',
        ]);
        $this->createMatch($serviceRequest, $provider, $oldOffer, 0.72);

        Http::fake([
            '*/rank' => Http::response([
                'results' => [
                    ['id' => $updatedOffer->id, 'semantic_score' => 0.96],
                    ['id' => $oldOffer->id, 'semantic_score' => 0.68],
                ],
            ]),
        ]);

        $this
            ->actingAs($provider)
            ->putJson(
                "/api/offers/{$updatedOffer->id}",
                $this->updateOfferPayload($updatedOffer, [
                    'title' => 'Réparation urgente de fuite de plomberie',
                    'description' => 'Intervention à domicile pour robinets et canalisations.',
                ]),
            )
            ->assertSuccessful();

        $this->assertDatabaseHas('service_request_matches', [
            'service_request_id' => $serviceRequest->id,
            'provider_id' => $provider->id,
            'offer_id' => $updatedOffer->id,
            'relevance_score' => 0.96,
        ]);
        Notification::assertNotSentTo($provider, ServiceRequestPublished::class);
    }

    public function test_updating_an_offer_removes_a_match_that_is_no_longer_valid(): void
    {
        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $this->createMatch($serviceRequest, $provider, $offer);
        Http::fake();

        $this
            ->actingAs($provider)
            ->putJson(
                "/api/offers/{$offer->id}",
                $this->updateOfferPayload($offer, ['status' => 'inactive']),
            )
            ->assertSuccessful();

        $this->assertDatabaseMissing('service_request_matches', [
            'service_request_id' => $serviceRequest->id,
            'provider_id' => $provider->id,
        ]);
        Http::assertNothingSent();
    }

    public function test_updating_only_the_price_does_not_recalculate_matches(): void
    {
        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $this->createMatch($serviceRequest, $provider, $offer, 0.87);
        Http::fake();

        $this
            ->actingAs($provider)
            ->putJson(
                "/api/offers/{$offer->id}",
                $this->updateOfferPayload($offer, ['price' => 275]),
            )
            ->assertSuccessful();

        $this->assertDatabaseHas('service_request_matches', [
            'service_request_id' => $serviceRequest->id,
            'provider_id' => $provider->id,
            'offer_id' => $offer->id,
            'relevance_score' => 0.87,
        ]);
        Http::assertNothingSent();
    }

    public function test_creating_an_offer_can_match_an_existing_open_request(): void
    {
        Notification::fake();

        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        $serviceRequest = $this->createServiceRequest($customer, $category, [
            'summary' => 'Réparer une fuite de plomberie à domicile à Marrakech.',
        ]);

        Http::fake(function (HttpRequest $request) {
            $offerId = $request['offers'][0]['id'];

            return Http::response([
                'results' => [[
                    'id' => $offerId,
                    'semantic_score' => 0.94,
                ]],
            ]);
        });

        $response = $this
            ->actingAs($provider)
            ->postJson('/api/offers', [
                'category_id' => $category->id,
                'title' => 'Réparation de fuite de plomberie',
                'description' => 'Intervention à domicile sur robinets et canalisations.',
                'type' => 'service',
                'service_duration_minutes' => 60,
                'at_customer_location' => true,
                'at_provider_location' => false,
                'price' => 250,
                'is_negotiable' => true,
                'status' => 'active',
                'city' => 'Marrakech',
                'location' => [
                    'latitude' => 31.6295,
                    'longitude' => -7.9811,
                ],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('service_request_matches', [
            'service_request_id' => $serviceRequest->id,
            'provider_id' => $provider->id,
            'offer_id' => $response->json('data.id'),
            'relevance_score' => 0.94,
        ]);
        Notification::assertSentToTimes(
            $provider,
            ServiceRequestPublished::class,
            1,
        );
    }

    public function test_deleting_a_matched_offer_selects_the_providers_next_best_offer(): void
    {
        Notification::fake();

        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        $deletedOffer = $this->createService(
            $provider,
            $category,
            title: 'Plomberie express',
        );
        $replacementOffer = $this->createService(
            $provider,
            $category,
            title: 'Réparation de fuite à domicile',
        );
        $serviceRequest = $this->createServiceRequest($customer, $category, [
            'summary' => 'Réparer une fuite de plomberie à domicile à Marrakech.',
        ]);
        $this->createMatch($serviceRequest, $provider, $deletedOffer, 0.91);

        Http::fake([
            '*/rank' => Http::response([
                'results' => [[
                    'id' => $replacementOffer->id,
                    'semantic_score' => 0.89,
                ]],
            ]),
        ]);

        $this
            ->actingAs($provider)
            ->deleteJson("/api/offers/{$deletedOffer->id}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('offers', ['id' => $deletedOffer->id]);
        $this->assertDatabaseHas('service_request_matches', [
            'service_request_id' => $serviceRequest->id,
            'provider_id' => $provider->id,
            'offer_id' => $replacementOffer->id,
            'relevance_score' => 0.89,
        ]);
        Notification::assertNotSentTo($provider, ServiceRequestPublished::class);
    }

    public function test_deleting_the_only_matched_offer_removes_the_match(): void
    {
        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $this->createMatch($serviceRequest, $provider, $offer);
        Http::fake();

        $this
            ->actingAs($provider)
            ->deleteJson("/api/offers/{$offer->id}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('service_request_matches', [
            'service_request_id' => $serviceRequest->id,
            'provider_id' => $provider->id,
        ]);
        Http::assertNothingSent();
    }

    public function test_house_cleaning_request_matches_above_the_adjusted_threshold_with_realistic_accented_text(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);

        $offer = $this->createService(
            $provider,
            $category,
            city: 'Marrakech',
            title: 'Grand ménage d’un appartement',
            description: 'Un nettoyage complet de votre appartement : cuisine, sanitaires, sols, '
                .'poussière et surfaces. Le matériel et les produits courants sont inclus. Idéal '
                .'avant un emménagement, après un départ ou pour un grand ménage saisonnier.',
        );

        // Measured against the real ranking service with properly accented French
        // text (2026-08-25): an earlier, ASCII-only test of this same scenario had
        // wrongly suggested it would score 0.34-0.40 and fall below the threshold —
        // that was an artifact of stripping accents before calling /rank, not a real
        // gap. With correct accents the real score is ~0.57.
        Http::fake([
            '*/rank' => Http::response([
                'results' => [
                    ['id' => $offer->id, 'semantic_score' => 0.5728],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($customer)
            ->postJson('/api/service-requests', [
                'raw_text' => 'Je cherche un grand ménage à domicile à Marrakech demain, budget 400 dh.',
                'summary' => 'Grand ménage à domicile à Marrakech.',
                'category_id' => $category->id,
                'city' => 'Marrakech',
                'desired_start_at' => '2026-08-26T09:00:00Z',
                'desired_end_at' => '2026-08-26T20:00:00Z',
                'budget_max' => 400,
                'at_home' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('service_request_matches', [
            'service_request_id' => $response->json('data.id'),
            'provider_id' => $provider->id,
            'offer_id' => $offer->id,
            'relevance_score' => 0.5728,
        ]);
        Notification::assertSentTo($provider, ServiceRequestPublished::class);
    }

    public function test_provider_can_send_and_update_a_compatible_proposal(): void
    {
        Notification::fake();

        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $this->createMatch($serviceRequest, $provider, $offer);

        $payload = [
            'offer_id' => $offer->id,
            'proposed_price' => 250,
            'scheduled_at' => '2026-08-25T10:00:00Z',
            'message' => 'Je peux intervenir le matin.',
        ];

        $this
            ->actingAs($provider)
            ->putJson("/api/provider/service-requests/{$serviceRequest->id}/proposal", $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $payload['proposed_price'] = 240;

        $this
            ->actingAs($provider)
            ->putJson("/api/provider/service-requests/{$serviceRequest->id}/proposal", $payload)
            ->assertSuccessful()
            ->assertJsonPath('data.proposed_price', '240.00');

        $this->assertDatabaseCount('service_request_proposals', 1);
        Notification::assertSentToTimes(
            $customer,
            ServiceRequestProposalReceived::class,
            2,
        );
    }

    public function test_provider_cannot_propose_an_offer_without_the_recorded_semantic_match(): void
    {
        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        $selectedOffer = $this->createService(
            $provider,
            $category,
            title: 'Nettoyage complet de maison',
        );
        $unselectedOffer = $this->createService(
            $provider,
            $category,
            title: 'Réparation de petit électroménager',
        );
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $this->createMatch($serviceRequest, $provider, $selectedOffer, 0.82);

        $this
            ->actingAs($provider)
            ->putJson("/api/provider/service-requests/{$serviceRequest->id}/proposal", [
                'offer_id' => $unselectedOffer->id,
                'proposed_price' => 250,
                'scheduled_at' => '2026-08-25T10:00:00Z',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('offer_id');

        $this->assertDatabaseCount('service_request_proposals', 0);
    }

    public function test_provider_cannot_use_another_users_offer(): void
    {
        $provider = User::factory()->create();
        $otherProvider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($otherProvider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);

        $this
            ->actingAs($provider)
            ->putJson("/api/provider/service-requests/{$serviceRequest->id}/proposal", [
                'offer_id' => $offer->id,
                'proposed_price' => 200,
                'scheduled_at' => '2026-08-25T10:00:00Z',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('service_request_proposals', 0);
    }

    public function test_provider_cannot_propose_a_service_with_an_incompatible_location_mode(): void
    {
        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        $offer = $this->createService(
            $provider,
            $category,
            atCustomerLocation: false,
            atProviderLocation: true,
        );
        $serviceRequest = $this->createServiceRequest($customer, $category, [
            'at_home' => true,
        ]);

        $this
            ->actingAs($provider)
            ->putJson("/api/provider/service-requests/{$serviceRequest->id}/proposal", [
                'offer_id' => $offer->id,
                'proposed_price' => 250,
                'scheduled_at' => '2026-08-25T10:00:00Z',
                'message' => 'Je peux intervenir le matin.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offer_id']);

        $this->assertDatabaseCount('service_request_proposals', 0);
    }

    public function test_proposal_must_respect_budget_and_requested_period(): void
    {
        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category, duration: 90);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $this->createMatch($serviceRequest, $provider, $offer);

        $this
            ->actingAs($provider)
            ->putJson("/api/provider/service-requests/{$serviceRequest->id}/proposal", [
                'offer_id' => $offer->id,
                'proposed_price' => 350,
                'scheduled_at' => '2026-08-25T10:00:00Z',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('proposed_price');

        $this
            ->actingAs($provider)
            ->putJson("/api/provider/service-requests/{$serviceRequest->id}/proposal", [
                'offer_id' => $offer->id,
                'proposed_price' => 250,
                'scheduled_at' => '2026-08-25T17:00:00Z',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at');

        $this->assertDatabaseCount('service_request_proposals', 0);
    }

    public function test_customer_can_accept_a_proposal_and_create_a_reservation(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $otherProvider = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category, duration: 90);
        $otherOffer = $this->createService($otherProvider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $acceptedProposal = $this->createProposal($serviceRequest, $provider, $offer);
        $declinedProposal = $this->createProposal(
            $serviceRequest,
            $otherProvider,
            $otherOffer,
            '2026-08-25 12:00:00',
            220,
        );

        $this
            ->actingAs($customer)
            ->postJson("/api/service-request-proposals/{$acceptedProposal->id}/accept")
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.agreed_price', '250.00')
            ->assertJsonPath('data.service_request_id', $serviceRequest->id);

        $this->assertDatabaseHas('reservations', [
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'service_request_id' => $serviceRequest->id,
            'service_request_proposal_id' => $acceptedProposal->id,
            'duration_minutes' => 90,
            'agreed_price' => 250,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => 'fulfilled',
        ]);
        $this->assertDatabaseHas('service_request_proposals', [
            'id' => $acceptedProposal->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('service_request_proposals', [
            'id' => $declinedProposal->id,
            'status' => 'declined',
        ]);
        Notification::assertSentTo($provider, ReservationCreated::class);
    }

    public function test_accepting_a_proposal_rechecks_provider_availability(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $proposal = $this->createProposal($serviceRequest, $provider, $offer);

        Reservation::create([
            'user_id' => $otherCustomer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => $proposal->scheduled_at,
            'duration_minutes' => 60,
            'status' => 'confirmed',
        ]);

        $this
            ->actingAs($customer)
            ->postJson("/api/service-request-proposals/{$proposal->id}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at');

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => 'open',
        ]);
    }

    public function test_accepting_a_proposal_rechecks_the_recorded_semantic_match(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $proposal = $this->createProposal($serviceRequest, $provider, $offer);

        $serviceRequest->matches()->delete();

        $this
            ->actingAs($customer)
            ->postJson("/api/service-request-proposals/{$proposal->id}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('offer');

        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('service_request_proposals', [
            'id' => $proposal->id,
            'status' => 'pending',
        ]);
    }

    public function test_another_customer_cannot_accept_the_proposal(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $proposal = $this->createProposal($serviceRequest, $provider, $offer);

        $this
            ->actingAs($otherCustomer)
            ->postJson("/api/service-request-proposals/{$proposal->id}/accept")
            ->assertForbidden();

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_customer_can_decline_a_pending_proposal(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $proposal = $this->createProposal($serviceRequest, $provider, $offer);

        $this
            ->actingAs($customer)
            ->patchJson("/api/service-request-proposals/{$proposal->id}/decline")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'declined');
    }

    public function test_customer_and_provider_share_a_private_conversation_with_proposal_context(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $proposal = $this->createProposal($serviceRequest, $provider, $offer);

        $response = $this
            ->actingAs($customer)
            ->postJson("/api/service-request-proposals/{$proposal->id}/conversation")
            ->assertCreated()
            ->assertJsonPath('proposal_context.proposal_id', $proposal->id)
            ->assertJsonPath('proposal_context.service_request_id', $serviceRequest->id)
            ->assertJsonPath('proposal_context.request_summary', $serviceRequest->summary)
            ->assertJsonPath('proposal_context.offer_title', $offer->title)
            ->assertJsonPath('proposal_context.proposed_price', '250.00')
            ->assertJsonPath('proposal_context.message', 'Je suis disponible pour cette intervention.');

        $conversationId = $response->json('id');

        $this
            ->actingAs($provider)
            ->postJson("/api/service-request-proposals/{$proposal->id}/conversation")
            ->assertSuccessful()
            ->assertJsonPath('id', $conversationId);

        $this
            ->actingAs($provider)
            ->getJson('/api/conversations')
            ->assertSuccessful()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $conversationId)
            ->assertJsonPath('0.proposal_context.proposal_id', $proposal->id)
            ->assertJsonPath('0.proposal_context.request_summary', $serviceRequest->summary);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'service_request_proposal_id' => $proposal->id,
        ]);
    }

    public function test_unrelated_user_cannot_open_a_proposal_conversation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $outsider = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $proposal = $this->createProposal($serviceRequest, $provider, $offer);

        $this
            ->actingAs($outsider)
            ->postJson("/api/service-request-proposals/{$proposal->id}/conversation")
            ->assertForbidden();

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_general_and_proposal_conversations_are_kept_separate(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $proposal = $this->createProposal($serviceRequest, $provider, $offer);

        $generalConversationId = $this
            ->actingAs($customer)
            ->postJson('/api/conversations', ['user_id' => $provider->id])
            ->assertCreated()
            ->json('id');

        $proposalConversationId = $this
            ->actingAs($customer)
            ->postJson("/api/service-request-proposals/{$proposal->id}/conversation")
            ->assertCreated()
            ->json('id');

        $this->assertNotSame($generalConversationId, $proposalConversationId);
        $this->assertDatabaseCount('conversations', 2);
        $this->assertNull(Conversation::findOrFail($generalConversationId)->service_request_proposal_id);
        $this->assertSame(
            $proposal->id,
            Conversation::findOrFail($proposalConversationId)->service_request_proposal_id,
        );
    }

    public function test_provider_can_withdraw_and_resubmit_a_proposal(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $proposal = $this->createProposal($serviceRequest, $provider, $offer);

        $this
            ->actingAs($provider)
            ->patchJson("/api/provider/service-request-proposals/{$proposal->id}/withdraw")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'withdrawn');

        $this
            ->actingAs($provider)
            ->putJson("/api/provider/service-requests/{$serviceRequest->id}/proposal", [
                'offer_id' => $offer->id,
                'proposed_price' => 230,
                'scheduled_at' => '2026-08-25T11:00:00Z',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseCount('service_request_proposals', 1);
    }

    public function test_expired_request_cannot_receive_a_proposal(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category, [
            'expires_at' => CarbonImmutable::parse('2026-08-24 05:00:00', 'UTC'),
        ]);
        $this->createMatch($serviceRequest, $provider, $offer);

        $this
            ->actingAs($provider)
            ->putJson("/api/provider/service-requests/{$serviceRequest->id}/proposal", [
                'offer_id' => $offer->id,
                'proposed_price' => 250,
                'scheduled_at' => '2026-08-25T10:00:00Z',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_request');

        $this->assertDatabaseCount('service_request_proposals', 0);
    }

    public function test_cancelling_a_request_closes_pending_proposals(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);
        $proposal = $this->createProposal($serviceRequest, $provider, $offer);

        $this
            ->actingAs($customer)
            ->patchJson("/api/service-requests/{$serviceRequest->id}/cancel")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('service_request_proposals', [
            'id' => $proposal->id,
            'status' => 'declined',
        ]);
    }
}
