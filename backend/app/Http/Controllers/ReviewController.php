<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\Review;
use App\Http\Requests\UpdateReviewRequest;

class ReviewController extends Controller
{

    public function index(Offer $offer)
    {
        $reviews = $offer->reviews()
            ->with('user')
            ->latest()
            ->paginate(15);

        return ReviewResource::collection($reviews);
    }

    public function store(StoreReviewRequest $request)
    {
        $reservation = Reservation::with('review')
            ->findOrFail($request->validated('reservation_id'));

        if ($reservation->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not allowed to review this reservation.',
            ], 403);
        }

        if ($reservation->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed reservations can be reviewed.',
            ], 422);
        }

        if ($reservation->review) {
            return response()->json([
                'message' => 'This reservation has already been reviewed.',
            ], 422);
        }

        $review = Review::create([
            'user_id' => $request->user()->id,
            'offer_id' => $reservation->offer_id,
            'reservation_id' => $reservation->id,
            'rating' => $request->validated('rating'),
            'comment' => $request->validated('comment'),
        ]);

        $review->load([
            'user',
            'offer',
            'reservation',
        ]);

        return (new ReviewResource($review))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateReviewRequest $request,
        Review $review
    ) {
        $this->authorize('update', $review);

        $review->update([
            'rating' => $request->validated('rating'),
            'comment' => $request->validated('comment'),
        ]);

        $review->load([
            'user',
            'offer',
            'reservation',
        ]);

        return new ReviewResource($review);
    }

    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully.',
        ]);
    }
}