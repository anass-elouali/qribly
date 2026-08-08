<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
            'price' => $this->price,

            'location' => $this->location ? [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ]:null,
            
            'category' => CategoryResource::make(
                $this->whenLoaded('category')
            ),
            
            'owner' => UserResource::make(
                $this->whenLoaded('user')
            ),

            'distance' => isset($this->distance)
                ? round($this->distance, 2)
                : null,
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
