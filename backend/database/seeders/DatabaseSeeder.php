<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            OfferSeeder::class,
            OfferImageSeeder::class,
            ProviderAvailabilitySeeder::class,
            ReservationSeeder::class,
            ReviewSeeder::class,
            FavoriteSeeder::class,
            ConversationSeeder::class,
            MessageSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
