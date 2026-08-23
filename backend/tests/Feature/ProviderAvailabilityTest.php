<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderAvailabilityTest extends TestCase
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

    private function createService(User $provider, int $duration = 60): Offer
    {
        $category = Category::firstOrCreate([
            'name' => 'Services',
        ]);

        $offer = new Offer([
            'category_id' => $category->id,
            'title' => 'Service test',
            'description' => 'Description du service test',
            'type' => 'service',
            'price' => 100,
            'is_negotiable' => false,
            'status' => 'active',
            'service_duration_minutes' => $duration,
        ]);

        $offer->user()->associate($provider);
        $offer->save();

        return $offer;
    }

    private function createMondayAvailability(User $provider): void
    {
        $provider->providerAvailabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
    }

    public function test_provider_can_publish_their_weekly_availability(): void
    {
        $provider = User::factory()->create();

        $response = $this
            ->actingAs($provider)
            ->putJson('/api/provider/availability', [
                'days' => [
                    [
                        'day_of_week' => 1,
                        'enabled' => true,
                        'start_time' => '09:00',
                        'end_time' => '17:00',
                    ],
                    [
                        'day_of_week' => 2,
                        'enabled' => false,
                        'start_time' => null,
                        'end_time' => null,
                    ],
                ],
            ]);

        $response
            ->assertSuccessful()
            ->assertJsonPath('configured', true)
            ->assertJsonCount(1, 'days')
            ->assertJsonPath('days.0.day_of_week', 1);

        $this->assertDatabaseHas('provider_availabilities', [
            'user_id' => $provider->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    public function test_provider_must_keep_at_least_one_available_day(): void
    {
        $provider = User::factory()->create();

        $this
            ->actingAs($provider)
            ->putJson('/api/provider/availability', [
                'days' => [
                    [
                        'day_of_week' => 1,
                        'enabled' => false,
                        'start_time' => null,
                        'end_time' => null,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('days');
    }

    public function test_offer_availability_excludes_overlapping_provider_reservations(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createService($provider);
        $otherOffer = $this->createService($provider);
        $this->createMondayAvailability($provider);

        Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $otherOffer->id,
            'scheduled_at' => CarbonImmutable::parse(
                '2026-08-24 09:30',
                'Africa/Casablanca',
            )->utc(),
            'duration_minutes' => 60,
            'status' => 'confirmed',
        ]);

        $response = $this->getJson(
            "/api/offers/{$offer->id}/availability?from=2026-08-24&days=1",
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('duration_minutes', 60)
            ->assertJsonPath('days.0.date', '2026-08-24')
            ->assertJsonCount(2, 'days.0.slots')
            ->assertJsonPath('days.0.slots.0.time', '10:30')
            ->assertJsonPath('days.0.slots.1.time', '11:00');
    }

    public function test_client_can_only_book_a_generated_slot_when_schedule_is_configured(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createService($provider, 90);
        $this->createMondayAvailability($provider);

        $validSlot = CarbonImmutable::parse(
            '2026-08-24 10:30',
            'Africa/Casablanca',
        );

        $this
            ->actingAs($customer)
            ->postJson("/api/offers/{$offer->id}/reservations", [
                'scheduled_at' => $validSlot->toIso8601String(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('reservations', [
            'offer_id' => $offer->id,
            'duration_minutes' => 90,
            'status' => 'pending',
        ]);

        $this
            ->actingAs($customer)
            ->postJson("/api/offers/{$offer->id}/reservations", [
                'scheduled_at' => CarbonImmutable::parse(
                    '2026-08-24 12:30',
                    'Africa/Casablanca',
                )->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at');
    }

    public function test_booking_is_rejected_when_it_overlaps_another_provider_service(): void
    {
        $firstCustomer = User::factory()->create();
        $secondCustomer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createService($provider);
        $otherOffer = $this->createService($provider, 90);
        $this->createMondayAvailability($provider);

        Reservation::create([
            'user_id' => $firstCustomer->id,
            'offer_id' => $otherOffer->id,
            'scheduled_at' => CarbonImmutable::parse(
                '2026-08-24 09:30',
                'Africa/Casablanca',
            )->utc(),
            'duration_minutes' => 90,
            'status' => 'pending',
        ]);

        $this
            ->actingAs($secondCustomer)
            ->postJson("/api/offers/{$offer->id}/reservations", [
                'scheduled_at' => CarbonImmutable::parse(
                    '2026-08-24 10:30',
                    'Africa/Casablanca',
                )->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at');

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_unconfigured_provider_keeps_manual_booking_compatibility(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createService($provider, 45);

        $this
            ->actingAs($customer)
            ->postJson("/api/offers/{$offer->id}/reservations", [
                'scheduled_at' => CarbonImmutable::parse('2026-08-25 14:15', 'UTC')
                    ->toIso8601String(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('reservations', [
            'offer_id' => $offer->id,
            'duration_minutes' => 45,
        ]);
    }
}
