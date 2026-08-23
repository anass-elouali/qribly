<?php

namespace Database\Seeders;

use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationCancelled;
use App\Notifications\ReservationCompleted;
use App\Notifications\ReservationConfirmed;
use App\Notifications\ReservationCreated;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->get()->keyBy('email');
        $reservations = Reservation::query()->get()->keyBy('notes');
        $notifications = [
            ['prestataire@qribly.test', ReservationCreated::class, 'reservation_created', 'Nouvelle réservation', 'Mehdi souhaite réserver votre service de grand ménage.', ReservationSeeder::NOTES['pending_cleaning'], 70, false],
            ['sara@qribly.test', ReservationCreated::class, 'reservation_created', 'Nouvelle réservation', 'Lina souhaite réserver un diagnostic pour son smartphone.', ReservationSeeder::NOTES['pending_phone'], 55, false],
            ['client@qribly.test', ReservationConfirmed::class, 'reservation_confirmed', 'Réservation confirmée', 'Votre entretien de climatisation a été confirmé par Nadia.', ReservationSeeder::NOTES['confirmed_air_conditioning'], 45, false],
            ['lina@qribly.test', ReservationConfirmed::class, 'reservation_confirmed', 'Réservation confirmée', 'Votre location de VTT a été confirmée par Youssef.', ReservationSeeder::NOTES['confirmed_bike'], 35, true],
            ['client@qribly.test', ReservationCompleted::class, 'reservation_completed', 'Prestation terminée', 'Votre cours de mathématiques est terminé. Vous pouvez maintenant laisser un avis.', ReservationSeeder::NOTES['completed_math'], 25, true],
            ['client@qribly.test', ReservationCancelled::class, 'reservation_cancelled', 'Réservation annulée', 'Votre livraison de colis a bien été annulée.', ReservationSeeder::NOTES['cancelled_delivery'], 15, false],
        ];

        foreach ($notifications as [$email, $class, $type, $title, $message, $notes, $minutesAgo, $read]) {
            $user = $users[$email];
            $reservation = $reservations[$notes];
            $timestamp = now()->subMinutes($minutesAgo);

            DB::table('notifications')->updateOrInsert(
                ['id' => $this->uuid("{$email}|{$type}|{$reservation->id}")],
                [
                    'type' => $class,
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => json_encode([
                        'type' => $type,
                        'title' => $title,
                        'message' => $message,
                        'reservation_id' => $reservation->id,
                        'offer_id' => $reservation->offer_id,
                        'scheduled_at' => $reservation->scheduled_at?->toIso8601String(),
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'read_at' => $read ? $timestamp->addMinutes(5) : null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            );
        }
    }

    private function uuid(string $key): string
    {
        $hex = hash('sha256', "qribly-demo|{$key}");

        return sprintf(
            '%s-%s-5%s-a%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            substr($hex, 17, 3),
            substr($hex, 20, 12),
        );
    }
}
