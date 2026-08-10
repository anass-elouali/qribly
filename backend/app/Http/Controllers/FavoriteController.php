<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfferResource;
use App\Models\Offer;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{

    public function index(Request $request) {
        $favorites = $request->user()
            ->favoriteOffers()
            ->with(['category','user','offerImages'])
            ->latest()
            ->paginate(15);

        return   OfferResource::collection($favorites);
    }

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