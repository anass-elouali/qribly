<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_one_id' => $this->user_one_id,
            'user_two_id' => $this->user_two_id,
            'user_one' => UserResource::make($this->whenLoaded('userOne')),
            'user_two' => UserResource::make($this->whenLoaded('userTwo')),
            'messages' => $this->whenLoaded('messages', function () {
                return $this->messages->map(fn ($message) => [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'sender_id' => $message->sender_id,
                    'body' => $message->body,
                    'read_at' => $message->read_at,
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at,
                ]);
            }),
            'proposal_context' => $this->whenLoaded(
                'serviceRequestProposal',
                function () {
                    $proposal = $this->serviceRequestProposal;

                    if (! $proposal) {
                        return null;
                    }

                    return [
                        'proposal_id' => $proposal->id,
                        'service_request_id' => $proposal->service_request_id,
                        'request_summary' => $proposal->serviceRequest->summary,
                        'offer_title' => $proposal->offer->title,
                        'proposed_price' => $proposal->proposed_price,
                        'scheduled_at' => $proposal->scheduled_at,
                        'message' => $proposal->message,
                        'status' => $proposal->status,
                    ];
                },
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
