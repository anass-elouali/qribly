<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreOfferRequest;
use App\Http\Requests\UpdateOfferRequest;
use App\Http\Resources\OfferResource;
use App\Models\Offer;



class OfferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Offer::with(['category', 'user']);

        $query->when($request->category, function($query) use ($request){
            $query->where('category_id', $request->category);
        });

        $offers = $query->latest()->paginate(10);
        

        return OfferResource::collection($offers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOfferRequest $request)
    {
        $offer = $request->user()->offers()->create(
            $request->validated()
        );

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
        $offer->load(['category', 'user']);
        return new OfferResource($offer);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOfferRequest $request, Offer $offer)
    {
        $this->authorize('update', $offer);
        $offer->update($request->validated());
        $offer->load(['category', 'user']);

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
