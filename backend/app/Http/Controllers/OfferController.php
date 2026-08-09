<?php

namespace App\Http\Controllers;


use App\Http\Requests\StoreOfferRequest;
use App\Http\Requests\UpdateOfferRequest;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\NearbyOfferRequest;
use App\Http\Requests\OfferIndexRequest;

class OfferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(OfferIndexRequest $request)
    {
        $filters = $request->validated();

        $query = Offer::withLocationCoordinates()
            ->with(['category', 'user', 'offerImages']);

        
        $query->when($filters['category'] ?? null, function ($query, $category) {
            $query->where('category_id', $category);
        });

        $query->when($filters['q'] ?? null, function ($query, $q) {
            $query->where(function ($query) use ($q) {
                $query->where('title', 'ilike', "%{$q}%")
                    ->orWhere('description', 'ilike', "%{$q}%");
            });
        });

        $query->when($filters['type'] ?? null, function ($query, $type) {
            $query->where('type', $type);
        });

        $query->when($filters['status'] ?? null, function ($query, $status) {
            $query->where('status', $status);
        });


        $query->when($filters['min_price'] ?? null, function ($query, $minPrice) {
            $query->where('price', '>=', $minPrice);
        });

        $query->when($filters['max_price'] ?? null, function ($query, $maxPrice) {
            $query->where('price', '<=', $maxPrice);
        });



        $offers = $query->latest()->paginate(10);
        

        return OfferResource::collection($offers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOfferRequest $request)
    {
        $data = $request->validated();

        // Extract location
        $latitude = $data['location']['latitude'];
        $longitude = $data['location']['longitude'];

        unset($data['location']);

        // Convert coordinates to PostGIS geography
        $location = DB::selectOne(
            'SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography AS location',
            [$longitude, $latitude]
        )->location;

        $data['location'] = $location;

        // Remove images from offer data
        $images = $data['images'] ?? [];
        unset($data['images']);

        // Create offer
        $offer = $request->user()->offers()->create($data);


        // Store images
        foreach ($images as $image) {
            $path = $image->store('offers', 'public');

            $offer->offerImages()->create([
                'path' => $path,
            ]);
        }


         // Load relationships
        $offer->load([
            'category',
            'user',
            'offerImages',
        ]);

    

        return (new OfferResource($offer))
            ->response()
            ->setStatusCode(201);

    }

    /**
     * Display the specified resource.
     */
    public function show(Offer $offer)
    {
        $offer = Offer::withLocationCoordinates()
            ->with(['category', 'user', 'offerImages'])
            ->findOrFail($offer->id);


        return new OfferResource($offer);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOfferRequest $request, Offer $offer)
    {
        $this->authorize('update', $offer);

        $data = $request->validated();

        if (isset($data['location'])) {
            $latitude = $data['location']['latitude'];
            $longitude = $data['location']['longitude'];

            unset($data['location']);

            $location = DB::selectOne(
                'SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography AS location',
                [$longitude, $latitude]
            )->location;

            $data['location'] = $location;
        }

        $offer->update($data);

        $offer = Offer::withLocationCoordinates()
            ->with(['category', 'user', 'offerImages'])
            ->findOrFail($offer->id);


        return (new OfferResource($offer));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Offer $offer)
    {
        $this->authorize('delete', $offer);
        $offer->delete();
         return response()->json([
            'message' => 'Offer deleted successfully'
        ]);
    }

    public function nearby(NearbyOfferRequest $request)
    {
        $latitude = $request->validated('latitude');
        $longitude = $request->validated('longitude');
        $radius = $request->validated('radius') * 1000;

        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

        $offers = Offer::withLocationCoordinates()
            ->with(['category', 'user', 'offerImages'])
            ->selectRaw(
                "ST_Distance(location, $point) AS distance",
                [$longitude, $latitude]
            )
            ->whereRaw(
                "ST_DWithin(location, $point, ?)",
                [$longitude, $latitude, $radius]
            )
            ->orderBy('distance')
            ->paginate(10);

        return OfferResource::collection($offers);
    }
    
}
