<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function store(Request $request, Offer $offer)
    {
        $request->user()
            ->favoriteOffers()
            ->syncWithoutDetaching([$offer->id]);

        return response()->json([
            'message' => 'Offer added to favorites',
        ], 201);
    }

    public function destroy(Request $request, Offer $offer)
    {
        $request->user()
            ->favoriteOffers()
            ->detach($offer->id);

        return response()->noContent();
    }
}