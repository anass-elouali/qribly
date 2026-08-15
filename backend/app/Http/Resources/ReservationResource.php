<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'scheduled_at' => $this->scheduled_at,

            'status' => $this->status,

            'notes' => $this->notes,

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'created_at' => $this->user->created_at,
                ];
            }),

            'offer' => $this->whenLoaded('offer', function () {
                return [
                    'id' => $this->offer->id,
                    'title' => $this->offer->title,
                    'type' => $this->offer->type,
                    'price' => $this->offer->price,
                ];
            }),

            'review' => new ReviewResource($this->whenLoaded('review')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
