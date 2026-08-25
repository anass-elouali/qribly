<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_request_id' => $this->service_request_id,
            'proposed_price' => $this->proposed_price,
            'scheduled_at' => $this->scheduled_at,
            'message' => $this->message,
            'status' => $this->status,
            'provider' => UserResource::make($this->whenLoaded('provider')),
            'offer' => $this->whenLoaded('offer', function () {
                return [
                    'id' => $this->offer->id,
                    'title' => $this->offer->title,
                    'price' => $this->offer->price,
                    'city' => $this->offer->city,
                    'service_duration_minutes' => $this->offer->service_duration_minutes,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
