<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReservationCreated extends Notification 
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
            'type' => 'reservation_created',
            'title' => 'New reservation',
            'message' => 'You have received a new reservation.',
            'reservation_id' => $this->reservation->id,
            'offer_id' => $this->reservation->offer_id,
            'scheduled_at' => $this->reservation->scheduled_at,
        ];
    }
}