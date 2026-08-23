<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OfferResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'price' => $this->price,
            'is_negotiable' => $this->is_negotiable,
            'status' => $this->status,
            'service_duration_minutes' => $this->service_duration_minutes,
            'city' => $this->city,

            'location' => $this->location ? [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ] : null,

            'category' => CategoryResource::make(
                $this->whenLoaded('category')
            ),

            'owner' => UserResource::make(
                $this->whenLoaded('user')
            ),

            'images' => $this->whenLoaded('offerImages', function () {
                return $this->offerImages->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => Storage::url($image->path),
                    ];
                });
            }),

            'distance' => isset($this->distance)
                ? round($this->distance, 2)
                : null,

            'semantic_score' => isset($this->semantic_score)
                ? round($this->semantic_score, 4)
                : null,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
