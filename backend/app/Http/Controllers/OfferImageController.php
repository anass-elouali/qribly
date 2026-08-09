<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOfferImageRequest;

use App\Models\Offer;
use App\Models\OfferImage;
use Illuminate\Support\Facades\Storage;

class OfferImageController extends Controller
{

     public function store(StoreOfferImageRequest $request, Offer $offer)
    {
        $this->authorize('update', $offer);

        foreach ($request->file('images') as $image) {
            $path = $image->store('offers', 'public');

            $offer->offerImages()->create([
                'path' => $path,
            ]);
        }

        $offer->load('offerImages');

        return response()->json([
            'images' => $offer->offerImages->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => Storage::url($image->path),
                ];
            }),
        ], 201);
    }

    public function destroy(Offer $offer, OfferImage $image) {
        $this->authorize('update',$offer);

        abort_unless(
            $image->offer_id === $offer->id,
            404
        );

        Storage::disk('public')->delete($image->path);

        $image->delete();

        return response()->noContent();
    }
}
