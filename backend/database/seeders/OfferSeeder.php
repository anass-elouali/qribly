<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfferSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->whereIn('email', array_column(DemoCatalog::USERS, 'email'))
            ->get()
            ->keyBy('email');

        $categories = Category::all()->keyBy('name');

        foreach (DemoCatalog::allOffers() as $data) {
            $user = $users[$data['user']];
            $category = $categories[$data['category']];

            $location = DB::selectOne(
                'SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography AS location',
                [$data['longitude'], $data['latitude']]
            )->location;

            $offer = Offer::firstOrNew(['title' => $data['title']]);
            $offer->fill([
                'category_id' => $category->id,
                'description' => $data['description'],
                'type' => $data['type'],
                'price' => $data['price'],
                'is_negotiable' => $data['is_negotiable'],
                'status' => $data['status'],
                'city' => $data['city'],
                'service_duration_minutes' => $data['duration'],
                'location' => $location,
            ]);
            $offer->user()->associate($user);
            $offer->save();
        }
    }
}
