<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-08-24 06:00:00', 'UTC'),
        );
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_demo_seeders_create_a_complete_french_dataset(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseCount('users', 7);
        $this->assertDatabaseCount('offers', 12);
        $this->assertDatabaseCount('offer_images', 60);
        $this->assertDatabaseCount('provider_availabilities', 26);
        $this->assertDatabaseCount('reservations', 11);
        $this->assertDatabaseCount('reviews', 5);
        $this->assertDatabaseCount('favorites', 8);
        $this->assertDatabaseCount('conversations', 3);
        $this->assertDatabaseCount('messages', 10);
        $this->assertDatabaseCount('notifications', 6);

        $this->assertDatabaseHas('categories', ['name' => 'Services à domicile']);
        $this->assertDatabaseHas('offers', [
            'title' => 'Grand ménage d’un appartement',
            'city' => 'Marrakech',
            'service_duration_minutes' => 120,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'client@qribly.test']);
        $this->assertDatabaseHas('reservations', ['status' => 'pending']);
        $this->assertDatabaseHas('reservations', ['status' => 'confirmed']);
        $this->assertDatabaseHas('reservations', ['status' => 'completed']);
        $this->assertDatabaseHas('reservations', ['status' => 'cancelled']);

        Offer::query()->with('offerImages')->each(function (Offer $offer) {
            $this->assertCount(5, $offer->offerImages);

            foreach ($offer->offerImages as $image) {
                Storage::disk('public')->assertExists($image->path);
            }
        });
    }

    public function test_demo_seeders_are_idempotent_and_busy_bookings_do_not_overlap(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('offers', 12);
        $this->assertDatabaseCount('offer_images', 60);
        $this->assertDatabaseCount('reservations', 11);
        $this->assertDatabaseCount('reviews', 5);
        $this->assertDatabaseCount('favorites', 8);
        $this->assertDatabaseCount('messages', 10);
        $this->assertDatabaseCount('notifications', 6);

        $busyReservations = Reservation::query()
            ->whereIn('status', ['pending', 'confirmed'])
            ->with('offer:id,user_id')
            ->get()
            ->groupBy(fn (Reservation $reservation) => $reservation->offer->user_id);

        foreach ($busyReservations as $providerReservations) {
            foreach ($providerReservations as $index => $reservation) {
                $start = CarbonImmutable::parse($reservation->scheduled_at);
                $end = $start->addMinutes($reservation->duration_minutes);

                foreach ($providerReservations->slice($index + 1) as $other) {
                    $otherStart = CarbonImmutable::parse($other->scheduled_at);
                    $otherEnd = $otherStart->addMinutes($other->duration_minutes);

                    $this->assertFalse(
                        $start->lessThan($otherEnd) && $end->greaterThan($otherStart),
                        "Les réservations {$reservation->id} et {$other->id} se chevauchent.",
                    );
                }
            }
        }
    }
}
