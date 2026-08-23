<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class ProviderAvailabilitySeeder extends Seeder
{
    /**
     * Carbon utilise 0 pour dimanche et 6 pour samedi.
     *
     * @var array<string, array<int, array{0: string, 1: string}>>
     */
    private const SCHEDULES = [
        'prestataire@qribly.test' => [
            1 => ['09:00', '18:00'],
            2 => ['09:00', '18:00'],
            3 => ['09:00', '18:00'],
            4 => ['09:00', '18:00'],
            5 => ['09:00', '18:00'],
            6 => ['10:00', '16:00'],
        ],
        'youssef@qribly.test' => [
            1 => ['08:30', '17:30'],
            2 => ['08:30', '17:30'],
            3 => ['08:30', '17:30'],
            4 => ['08:30', '17:30'],
            5 => ['08:30', '17:30'],
        ],
        'sara@qribly.test' => [
            2 => ['10:00', '19:00'],
            3 => ['10:00', '19:00'],
            4 => ['10:00', '19:00'],
            5 => ['10:00', '19:00'],
            6 => ['10:00', '19:00'],
        ],
        'omar@qribly.test' => [
            1 => ['14:00', '20:00'],
            3 => ['14:00', '20:00'],
            5 => ['14:00', '20:00'],
            6 => ['09:00', '13:00'],
        ],
        'salma@qribly.test' => [
            0 => ['10:00', '16:00'],
            2 => ['10:00', '18:00'],
            3 => ['10:00', '18:00'],
            4 => ['10:00', '18:00'],
            5 => ['10:00', '18:00'],
            6 => ['10:00', '18:00'],
        ],
    ];

    public function run(): void
    {
        foreach (self::SCHEDULES as $email => $days) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $user->providerAvailabilities()
                ->whereNotIn('day_of_week', array_keys($days))
                ->delete();

            foreach ($days as $dayOfWeek => [$startTime, $endTime]) {
                $user->providerAvailabilities()->updateOrCreate(
                    ['day_of_week' => $dayOfWeek],
                    ['start_time' => $startTime, 'end_time' => $endTime],
                );
            }
        }
    }
}
