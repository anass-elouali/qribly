<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReservationCancelled extends Notification
{
    use Queueable;

    public function __construct(
        public Reservation $reservation
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reservation_cancelled',
            'title' => 'Reservation cancelled',
            'message' => 'A reservation has been cancelled.',
            'reservation_id' => $this->reservation->id,
            'offer_id' => $this->reservation->offer_id,
            'scheduled_at' => $this->reservation->scheduled_at,
        ];
    }
}