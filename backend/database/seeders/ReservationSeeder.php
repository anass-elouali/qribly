<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\Reservation;
use App\Models\User;
use App\Services\OfferAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class ReservationSeeder extends Seeder
{
    public const NOTES = [
        'pending_cleaning' => 'Merci de prévoir le nettoyage de la cuisine et des deux salles de bain.',
        'pending_phone' => 'Le téléphone ne charge plus depuis hier, même avec un autre câble.',
        'confirmed_air_conditioning' => 'La climatisation du salon fait du bruit après quelques minutes.',
        'confirmed_bike' => 'Je souhaite un VTT taille M et un casque, si possible.',
        'completed_math' => 'Révision des fonctions et préparation du prochain devoir surveillé.',
        'completed_hair' => 'Brushing souple pour une cérémonie familiale.',
        'completed_cleaning' => 'Grand ménage après le départ des anciens locataires.',
        'completed_delivery' => 'Petit colis fragile à livrer au centre-ville.',
        'completed_chef' => 'Menu pour six personnes, sans fruits à coque.',
        'cancelled_delivery' => 'La livraison n’est finalement plus nécessaire.',
        'cancelled_chef' => 'Le nombre d’invités a changé, nous reporterons le repas.',
    ];

    public function run(): void
    {
        $users = User::query()
            ->whereIn('email', array_column(DemoCatalog::USERS, 'email'))
            ->get()
            ->keyBy('email');
        $offers = Offer::query()
            ->whereIn('title', array_column(DemoCatalog::OFFERS, 'title'))
            ->get()
            ->keyBy('title');
        $now = CarbonImmutable::now(OfferAvailabilityService::TIMEZONE);

        $reservations = [
            [
                'client' => 'client@qribly.test',
                'offer' => 'Grand ménage d’un appartement',
                'scheduled_at' => $this->occurrence(1, '09:00', true),
                'status' => 'pending',
                'notes' => self::NOTES['pending_cleaning'],
            ],
            [
                'client' => 'lina@qribly.test',
                'offer' => 'Réparation de smartphone',
                'scheduled_at' => $this->occurrence(2, '10:00', true),
                'status' => 'pending',
                'notes' => self::NOTES['pending_phone'],
            ],
            [
                'client' => 'client@qribly.test',
                'offer' => 'Entretien et nettoyage de climatisation',
                'scheduled_at' => $this->occurrence(3, '13:00', true),
                'status' => 'confirmed',
                'notes' => self::NOTES['confirmed_air_conditioning'],
            ],
            [
                'client' => 'lina@qribly.test',
                'offer' => 'Location de VTT dans la vallée de l’Ourika',
                'scheduled_at' => $this->occurrence(5, '09:00', true),
                'status' => 'confirmed',
                'notes' => self::NOTES['confirmed_bike'],
            ],
            [
                'client' => 'client@qribly.test',
                'offer' => 'Cours de soutien en mathématiques',
                'scheduled_at' => $this->occurrence(3, '15:00', false),
                'status' => 'completed',
                'notes' => self::NOTES['completed_math'],
            ],
            [
                'client' => 'lina@qribly.test',
                'offer' => 'Coiffure et brushing à domicile',
                'scheduled_at' => $this->occurrence(6, '11:00', false),
                'status' => 'completed',
                'notes' => self::NOTES['completed_hair'],
            ],
            [
                'client' => 'lina@qribly.test',
                'offer' => 'Grand ménage d’un appartement',
                'scheduled_at' => $this->occurrence(1, '13:00', false, 1),
                'status' => 'completed',
                'notes' => self::NOTES['completed_cleaning'],
            ],
            [
                'client' => 'client@qribly.test',
                'offer' => 'Livraison express de petits colis',
                'scheduled_at' => $this->occurrence(4, '10:00', false),
                'status' => 'completed',
                'notes' => self::NOTES['completed_delivery'],
            ],
            [
                'client' => 'lina@qribly.test',
                'offer' => 'Chef à domicile — menu marocain',
                'scheduled_at' => $this->occurrence(6, '12:00', false, 1),
                'status' => 'completed',
                'notes' => self::NOTES['completed_chef'],
            ],
            [
                'client' => 'client@qribly.test',
                'offer' => 'Livraison express de petits colis',
                'scheduled_at' => $this->occurrence(4, '14:00', true),
                'status' => 'cancelled',
                'notes' => self::NOTES['cancelled_delivery'],
                'cancelled_by' => 'client@qribly.test',
                'cancelled_at' => $now->subHours(3),
            ],
            [
                'client' => 'lina@qribly.test',
                'offer' => 'Chef à domicile — menu marocain',
                'scheduled_at' => $this->occurrence(6, '12:00', true),
                'status' => 'cancelled',
                'notes' => self::NOTES['cancelled_chef'],
                'cancelled_by' => 'prestataire@qribly.test',
                'cancelled_at' => $now->subDay(),
            ],
        ];

        foreach ($reservations as $data) {
            $offer = $offers[$data['offer']];
            $client = $users[$data['client']];

            Reservation::updateOrCreate(
                [
                    'user_id' => $client->id,
                    'offer_id' => $offer->id,
                    'notes' => $data['notes'],
                ],
                [
                    'scheduled_at' => $data['scheduled_at']->utc(),
                    'duration_minutes' => $offer->service_duration_minutes,
                    'status' => $data['status'],
                    'cancelled_by' => isset($data['cancelled_by'])
                        ? $users[$data['cancelled_by']]->id
                        : null,
                    'cancelled_at' => $data['cancelled_at'] ?? null,
                ],
            );
        }
    }

    private function occurrence(
        int $dayOfWeek,
        string $time,
        bool $future,
        int $weekOffset = 0,
    ): CarbonImmutable {
        $date = CarbonImmutable::now(OfferAvailabilityService::TIMEZONE)->startOfDay();

        do {
            $date = $future ? $date->addDay() : $date->subDay();
        } while ($date->dayOfWeek !== $dayOfWeek);

        $date = $future ? $date->addWeeks($weekOffset) : $date->subWeeks($weekOffset);

        return $date->setTimeFromTimeString($time);
    }
}
