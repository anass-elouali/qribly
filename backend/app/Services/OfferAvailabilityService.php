<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class OfferAvailabilityService
{
    public const TIMEZONE = 'Africa/Casablanca';

    private const SLOT_INTERVAL_MINUTES = 30;

    private const MAX_SERVICE_DURATION_MINUTES = 480;

    /**
     * @return array{configured: bool, timezone: string, duration_minutes: int, days: array<int, array<string, mixed>>}
     */
    public function slots(Offer $offer, CarbonImmutable $from, int $days): array
    {
        $offer->loadMissing('user.providerAvailabilities');

        $availabilities = $offer->user->providerAvailabilities->keyBy('day_of_week');
        $duration = $this->durationFor($offer);

        if ($availabilities->isEmpty()) {
            return [
                'configured' => false,
                'timezone' => self::TIMEZONE,
                'duration_minutes' => $duration,
                'days' => [],
            ];
        }

        $rangeStart = $from->setTimezone(self::TIMEZONE)->startOfDay();
        $rangeEnd = $rangeStart->addDays($days);
        $busyReservations = $this->busyReservations(
            $offer,
            $rangeStart->subMinutes(self::MAX_SERVICE_DURATION_MINUTES),
            $rangeEnd,
        );
        $now = CarbonImmutable::now('UTC');
        $result = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $rangeStart->addDays($offset);
            $availability = $availabilities->get($date->dayOfWeek);
            $slots = [];

            if ($availability) {
                $windowStart = $date->setTimeFromTimeString($availability->start_time);
                $windowEnd = $date->setTimeFromTimeString($availability->end_time);

                for (
                    $cursor = $windowStart;
                    $cursor->addMinutes($duration)->lessThanOrEqualTo($windowEnd);
                    $cursor = $cursor->addMinutes(self::SLOT_INTERVAL_MINUTES)
                ) {
                    $startsAt = $cursor->utc();
                    $endsAt = $startsAt->addMinutes($duration);

                    if ($startsAt->lessThanOrEqualTo($now)) {
                        continue;
                    }

                    if ($this->overlaps($busyReservations, $startsAt, $endsAt)) {
                        continue;
                    }

                    $slots[] = [
                        'starts_at' => $startsAt->toIso8601String(),
                        'time' => $cursor->format('H:i'),
                    ];
                }
            }

            $result[] = [
                'date' => $date->toDateString(),
                'slots' => $slots,
            ];
        }

        return [
            'configured' => true,
            'timezone' => self::TIMEZONE,
            'duration_minutes' => $duration,
            'days' => $result,
        ];
    }

    public function isSlotAvailable(Offer $offer, CarbonInterface $scheduledAt): bool
    {
        $offer->loadMissing('user.providerAvailabilities');

        $startsAt = CarbonImmutable::parse($scheduledAt)->utc();
        $duration = $this->durationFor($offer);
        $endsAt = $startsAt->addMinutes($duration);

        if ($startsAt->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            return false;
        }

        $availabilities = $offer->user->providerAvailabilities->keyBy('day_of_week');

        if ($availabilities->isNotEmpty()) {
            $localStart = $startsAt->setTimezone(self::TIMEZONE);
            $availability = $availabilities->get($localStart->dayOfWeek);

            if (! $availability) {
                return false;
            }

            $windowStart = $localStart->startOfDay()->setTimeFromTimeString($availability->start_time);
            $windowEnd = $localStart->startOfDay()->setTimeFromTimeString($availability->end_time);
            $offsetMinutes = (int) $windowStart->diffInMinutes($localStart, false);

            if (
                $localStart->second !== 0
                || $offsetMinutes < 0
                || $offsetMinutes % self::SLOT_INTERVAL_MINUTES !== 0
                || $localStart->addMinutes($duration)->greaterThan($windowEnd)
            ) {
                return false;
            }
        }

        $busyReservations = $this->busyReservations(
            $offer,
            $startsAt->subMinutes(self::MAX_SERVICE_DURATION_MINUTES),
            $endsAt,
        );

        return ! $this->overlaps($busyReservations, $startsAt, $endsAt);
    }

    private function durationFor(Offer $offer): int
    {
        return $offer->service_duration_minutes ?: 60;
    }

    /**
     * @return Collection<int, Reservation>
     */
    private function busyReservations(
        Offer $offer,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
    ): Collection {
        return Reservation::query()
            ->whereHas('offer', function ($query) use ($offer) {
                $query->where('user_id', $offer->user_id);
            })
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('scheduled_at', '>=', $rangeStart->utc())
            ->where('scheduled_at', '<', $rangeEnd->utc())
            ->get(['id', 'scheduled_at', 'duration_minutes']);
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     */
    private function overlaps(
        Collection $reservations,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): bool {
        return $reservations->contains(function (Reservation $reservation) use ($startsAt, $endsAt) {
            $busyStart = CarbonImmutable::parse($reservation->scheduled_at)->utc();
            $busyEnd = $busyStart->addMinutes($reservation->duration_minutes ?: 60);

            return $busyStart->lessThan($endsAt) && $busyEnd->greaterThan($startsAt);
        });
    }
}
