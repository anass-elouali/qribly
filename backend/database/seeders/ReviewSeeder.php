<?php

namespace Database\Seeders;

use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $reviews = [
            ReservationSeeder::NOTES['completed_math'] => [
                'rating' => 5,
                'comment' => 'Omar explique clairement et prend le temps de vérifier que chaque notion est comprise.',
            ],
            ReservationSeeder::NOTES['completed_hair'] => [
                'rating' => 4,
                'comment' => 'Très ponctuelle et résultat soigné. Le brushing a bien tenu toute la soirée.',
            ],
            ReservationSeeder::NOTES['completed_cleaning'] => [
                'rating' => 5,
                'comment' => 'Appartement impeccable et travail très organisé. Je réserverai de nouveau sans hésiter.',
            ],
            ReservationSeeder::NOTES['completed_delivery'] => [
                'rating' => 4,
                'comment' => 'Livraison rapide et colis remis en bon état. Communication efficace.',
            ],
            ReservationSeeder::NOTES['completed_chef'] => [
                'rating' => 5,
                'comment' => 'Repas généreux, très bon tajine et cuisine laissée propre après le service.',
            ],
        ];

        foreach ($reviews as $notes => $data) {
            $reservation = Reservation::query()
                ->where('notes', $notes)
                ->where('status', 'completed')
                ->firstOrFail();

            Review::updateOrCreate(
                ['reservation_id' => $reservation->id],
                [
                    'user_id' => $reservation->user_id,
                    'offer_id' => $reservation->offer_id,
                    'rating' => $data['rating'],
                    'comment' => $data['comment'],
                ],
            );
        }
    }
}
