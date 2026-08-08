<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreOfferRequest;
use App\Http\Requests\UpdateOfferRequest;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;


class OfferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Offer::withLocationCoordinates()
            ->with(['category', 'user']);

        $query->when($request->category, function($query) use ($request){
            $query->where('category_id', $request->category);
            
        });

        $query->when($request->q, function ($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->where('title', 'ilike', '%' . $request->q . '%')
                        ->orWhere('description', 'ilike', '%' . $request->q . '%');
            });
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

        $latitude = $data['location']['latitude'];
        $longitude = $data['location']['longitude'];

        unset($data['location']);

        $location = DB::selectOne(
            'SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography AS location',
            [$longitude, $latitude]
        )->location;

        $data['location'] = $location;

        $offer = $request->user()->offers()->create($data);

        $offer->load(['category', 'user']);

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
            ->with(['category', 'user'])
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
            ->with(['category', 'user'])
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

   
    
}
