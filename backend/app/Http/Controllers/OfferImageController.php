<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Offer;
use App\Models\OfferImage;
use Illuminate\Support\Facades\Storage;

class OfferImageController extends Controller
{
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
