<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private function createOffer(User $provider): Offer
    {
        $category = Category::create([
            'name' => 'Test Category',
        ]);

        $offer = new Offer([
            'category_id' => $category->id,
            'title' => 'Test Service',
            'description' => 'Test service description',
            'type' => 'service',
            'price' => 100,
            'is_negotiable' => false,
            'status' => 'active',
        ]);

        $offer->user()->associate($provider);
        $offer->save();

        return $offer;
    }

    public function test_authenticated_user_can_create_a_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();

        $offer = $this->createOffer($provider);

        $response = $this
            ->actingAs($customer)
            ->postJson("/api/offers/{$offer->id}/reservations", [
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'notes' => 'Test reservation',
            ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('reservations', [
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'status' => 'pending',
            'notes' => 'Test reservation',
        ]);
    }

    public function test_reservation_starts_as_pending(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();

        $offer = $this->createOffer($provider);

        $this
            ->actingAs($customer)
            ->postJson("/api/offers/{$offer->id}/reservations", [
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ]);

        $reservation = Reservation::first();

        $this->assertNotNull($reservation);
        $this->assertSame('pending', $reservation->status);
    }

    public function test_reservation_time_with_an_offset_is_stored_in_utc(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $scheduledAt = now()->addDay()->startOfMinute()->setTimezone('Africa/Casablanca');

        $this
            ->actingAs($customer)
            ->postJson("/api/offers/{$offer->id}/reservations", [
                'scheduled_at' => $scheduledAt->toIso8601String(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('reservations', [
            'offer_id' => $offer->id,
            'scheduled_at' => $scheduledAt->copy()->utc()->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_user_cannot_reserve_their_own_offer(): void
    {
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);

        $this
            ->actingAs($provider)
            ->postJson("/api/offers/{$offer->id}/reservations", [
                'scheduled_at' => now()->addDay()->toIso8601String(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_inactive_offer_cannot_be_reserved(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $offer->update(['status' => 'inactive']);

        $this
            ->actingAs($customer)
            ->postJson("/api/offers/{$offer->id}/reservations", [
                'scheduled_at' => now()->addDay()->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('offer');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_occupied_slot_cannot_be_reserved_twice(): void
    {
        $firstCustomer = User::factory()->create();
        $secondCustomer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $scheduledAt = now()->addDay()->startOfMinute();

        Reservation::create([
            'user_id' => $firstCustomer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
        ]);

        $this
            ->actingAs($secondCustomer)
            ->postJson("/api/offers/{$offer->id}/reservations", [
                'scheduled_at' => $scheduledAt->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at');

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_cancelled_slot_can_be_reserved_again(): void
    {
        $firstCustomer = User::factory()->create();
        $secondCustomer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $scheduledAt = now()->addDay()->startOfMinute();

        Reservation::create([
            'user_id' => $firstCustomer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'cancelled',
        ]);

        $this
            ->actingAs($secondCustomer)
            ->postJson("/api/offers/{$offer->id}/reservations", [
                'scheduled_at' => $scheduledAt->toIso8601String(),
            ])
            ->assertCreated();

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_customer_can_cancel_their_pending_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $this
            ->actingAs($customer)
            ->patchJson("/api/reservations/{$reservation->id}/cancel")
            ->assertSuccessful()
            ->assertJsonPath('reservation.status', 'cancelled');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
            'cancelled_by' => $customer->id,
        ]);
        $this->assertNotNull($reservation->fresh()->cancelled_at);
    }

    public function test_customer_can_cancel_their_confirmed_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'confirmed',
        ]);

        $this
            ->actingAs($customer)
            ->patchJson("/api/reservations/{$reservation->id}/cancel")
            ->assertSuccessful();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
            'cancelled_by' => $customer->id,
        ]);
    }

    public function test_customer_cannot_cancel_another_users_reservation(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $this
            ->actingAs($otherCustomer)
            ->patchJson("/api/reservations/{$reservation->id}/cancel")
            ->assertForbidden();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'pending',
        ]);
    }

    public function test_customer_cannot_cancel_a_completed_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->subDay(),
            'status' => 'completed',
        ]);

        $this
            ->actingAs($customer)
            ->patchJson("/api/reservations/{$reservation->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cette réservation ne peut plus être annulée.');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'completed',
        ]);
    }

    public function test_provider_can_confirm_a_pending_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();

        $offer = $this->createOffer($provider);

        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($provider)
            ->patchJson("/api/provider/reservations/{$reservation->id}/confirm");

        $response->assertSuccessful();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_provider_can_cancel_a_pending_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();

        $offer = $this->createOffer($provider);

        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($provider)
            ->patchJson("/api/provider/reservations/{$reservation->id}/cancel");

        $response->assertSuccessful();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
            'cancelled_by' => $provider->id,
        ]);
    }

    public function test_provider_can_complete_a_confirmed_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();

        $offer = $this->createOffer($provider);

        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'confirmed',
        ]);

        $response = $this
            ->actingAs($provider)
            ->patchJson("/api/provider/reservations/{$reservation->id}/complete");

        $response->assertSuccessful();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'completed',
        ]);
    }

    public function test_other_provider_cannot_confirm_a_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $otherProvider = User::factory()->create();

        $offer = $this->createOffer($provider);

        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($otherProvider)
            ->patchJson("/api/provider/reservations/{$reservation->id}/confirm");

        $response->assertForbidden();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'pending',
        ]);
    }

    public function test_provider_can_cancel_a_confirmed_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'confirmed',
        ]);

        $this
            ->actingAs($provider)
            ->patchJson("/api/provider/reservations/{$reservation->id}/cancel")
            ->assertSuccessful();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
            'cancelled_by' => $provider->id,
        ]);
    }

    public function test_provider_cannot_confirm_a_non_pending_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'confirmed',
        ]);

        $this
            ->actingAs($provider)
            ->patchJson("/api/provider/reservations/{$reservation->id}/confirm")
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Seules les réservations en attente peuvent être confirmées.'
            );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_provider_cannot_complete_a_pending_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $this
            ->actingAs($provider)
            ->patchJson("/api/provider/reservations/{$reservation->id}/complete")
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Seules les réservations confirmées peuvent être terminées.'
            );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'pending',
        ]);
    }

    public function test_provider_cannot_cancel_a_completed_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->subDay(),
            'status' => 'completed',
        ]);

        $this
            ->actingAs($provider)
            ->patchJson("/api/provider/reservations/{$reservation->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cette réservation ne peut plus être annulée.');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'completed',
        ]);
    }

    public function test_other_provider_cannot_cancel_a_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $otherProvider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $this
            ->actingAs($otherProvider)
            ->patchJson("/api/provider/reservations/{$reservation->id}/cancel")
            ->assertForbidden();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'pending',
        ]);
    }

    public function test_other_provider_cannot_complete_a_reservation(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $otherProvider = User::factory()->create();
        $offer = $this->createOffer($provider);
        $reservation = Reservation::create([
            'user_id' => $customer->id,
            'offer_id' => $offer->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'confirmed',
        ]);

        $this
            ->actingAs($otherProvider)
            ->patchJson("/api/provider/reservations/{$reservation->id}/complete")
            ->assertForbidden();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
        ]);
    }
}
