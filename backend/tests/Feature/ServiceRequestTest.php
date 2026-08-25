<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestProposal;
use App\Models\User;
use App\Notifications\ReservationCreated;
use App\Notifications\ServiceRequestProposalReceived;
use App\Notifications\ServiceRequestPublished;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    ): Offer {
        $offer = new Offer([
            'category_id' => $category->id,
            'title' => 'Service à domicile',
            'description' => 'Description complète du service proposé.',
            'type' => 'service',
            'price' => 250,
            'is_negotiable' => true,
            'status' => 'active',
            'city' => $city,
            'service_duration_minutes' => $duration,
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
        return $serviceRequest->proposals()->create([
            'provider_id' => $provider->id,
            'offer_id' => $offer->id,
            'proposed_price' => $price,
            'scheduled_at' => CarbonImmutable::parse($scheduledAt, 'UTC'),
            'message' => 'Je suis disponible pour cette intervention.',
            'status' => 'pending',
        ]);
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
        $otherProvider = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);

        $this->createService($compatibleProvider, $category);
        $this->createService($otherProvider, $category, 'Rabat');

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

        Notification::assertSentTo($compatibleProvider, ServiceRequestPublished::class);
        Notification::assertNotSentTo($otherProvider, ServiceRequestPublished::class);
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
        $this->createService($provider, $category);

        $compatible = $this->createServiceRequest($customer, $category);
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

    public function test_provider_can_send_and_update_a_compatible_proposal(): void
    {
        Notification::fake();

        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        $offer = $this->createService($provider, $category);
        $serviceRequest = $this->createServiceRequest($customer, $category);

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

    public function test_proposal_must_respect_budget_and_requested_period(): void
    {
        $provider = User::factory()->create();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Services']);
        $offer = $this->createService($provider, $category, duration: 90);
        $serviceRequest = $this->createServiceRequest($customer, $category);

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
