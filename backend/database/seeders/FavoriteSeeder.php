<?php

namespace Database\Seeders;

use App\Models\Favorite;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $pairs = [
            ['client@qribly.test', 'Grand ménage d’un appartement'],
            ['client@qribly.test', 'Réparation de smartphone'],
            ['client@qribly.test', 'Panier de légumes bio de saison'],
            ['client@qribly.test', 'Caftan marocain brodé à la main'],
            ['lina@qribly.test', 'Coiffure et brushing à domicile'],
            ['lina@qribly.test', 'Location de VTT dans la vallée de l’Ourika'],
            ['lina@qribly.test', 'Chef à domicile — menu marocain'],
            ['lina@qribly.test', 'PC portable reconditionné'],
        ];

        $users = User::query()->get()->keyBy('email');
        $offers = Offer::query()->get()->keyBy('title');

        foreach ($pairs as [$email, $title]) {
            Favorite::updateOrCreate([
                'user_id' => $users[$email]->id,
                'offer_id' => $offers[$title]->id,
            ]);
        }
    }
}
