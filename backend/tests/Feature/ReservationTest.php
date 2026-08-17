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
}