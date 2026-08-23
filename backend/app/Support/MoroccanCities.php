<?php

namespace App\Support;

final class MoroccanCities
{
    /** @var array<int, array{name: string, latitude: float, longitude: float}> */
    public const ALL = [
        ['name' => 'Agadir', 'latitude' => 30.4278, 'longitude' => -9.5981],
        ['name' => 'Casablanca', 'latitude' => 33.5731, 'longitude' => -7.5898],
        ['name' => 'Essaouira', 'latitude' => 31.5085, 'longitude' => -9.7595],
        ['name' => 'Fès', 'latitude' => 34.0181, 'longitude' => -5.0078],
        ['name' => 'Marrakech', 'latitude' => 31.6295, 'longitude' => -7.9811],
        ['name' => 'Meknès', 'latitude' => 33.8935, 'longitude' => -5.5473],
        ['name' => 'Oujda', 'latitude' => 34.6814, 'longitude' => -1.9086],
        ['name' => 'Ourika', 'latitude' => 31.3742, 'longitude' => -7.7778],
        ['name' => 'Rabat', 'latitude' => 34.0209, 'longitude' => -6.8416],
        ['name' => 'Tanger', 'latitude' => 35.7595, 'longitude' => -5.8340],
        ['name' => 'Tétouan', 'latitude' => 35.5889, 'longitude' => -5.3626],
    ];

    public static function nearest(float $latitude, float $longitude): string
    {
        $nearest = self::ALL[0];
        $shortestDistance = INF;
        $longitudeScale = cos(deg2rad($latitude));

        foreach (self::ALL as $city) {
            $latitudeDelta = $latitude - $city['latitude'];
            $longitudeDelta = ($longitude - $city['longitude']) * $longitudeScale;
            $distance = ($latitudeDelta ** 2) + ($longitudeDelta ** 2);

            if ($distance < $shortestDistance) {
                $nearest = $city;
                $shortestDistance = $distance;
            }
        }

        return $nearest['name'];
    }
}
