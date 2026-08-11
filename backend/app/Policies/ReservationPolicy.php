<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function confirm(User $user, Reservation $reservation): bool
    {
        return $reservation->offer->user_id === $user->id;
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return $reservation->offer->user_id === $user->id;
    }

    public function complete(User $user, Reservation $reservation): bool
    {
        return $reservation->offer->user_id === $user->id;
    }
}