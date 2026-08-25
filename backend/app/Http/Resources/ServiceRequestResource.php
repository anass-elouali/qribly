<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'raw_text' => $this->when(
                $request->user()?->id === $this->user_id,
                $this->raw_text,
            ),
            'summary' => $this->summary,
            'city' => $this->city,
            'desired_start_at' => $this->desired_start_at,
            'desired_end_at' => $this->desired_end_at,
            'budget_max' => $this->budget_max,
            'at_home' => $this->at_home,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'customer' => UserResource::make($this->whenLoaded('user')),
            'proposals' => ServiceRequestProposalResource::collection(
                $this->whenLoaded('proposals')
            ),
            'proposals_count' => $this->whenCounted('proposals'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
