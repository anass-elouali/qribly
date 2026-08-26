<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\ServiceRequestProposal;
use App\Models\User;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->with([
                'userOne',
                'userTwo',
                'serviceRequestProposal.serviceRequest',
                'serviceRequestProposal.offer',
                'messages' => function ($query) {
                    $query->latest()->limit(1);
                },
            ])
            ->latest('updated_at')
            ->get();

        return response()->json(
            $conversations->map(
                fn (Conversation $conversation) => $this->resource($request, $conversation),
            ),
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $currentUser = $request->user();
        $otherUserId = $validated['user_id'];

        if ($currentUser->id === $otherUserId) {
            return response()->json([
                'message' => 'You cannot start a conversation with yourself.',
            ], 422);
        }

        $conversation = Conversation::query()
            ->whereNull('service_request_proposal_id')
            ->where(function ($query) use ($currentUser, $otherUserId) {
                $query
                    ->where(function ($participants) use ($currentUser, $otherUserId) {
                        $participants->where('user_one_id', $currentUser->id)
                            ->where('user_two_id', $otherUserId);
                    })
                    ->orWhere(function ($participants) use ($currentUser, $otherUserId) {
                        $participants->where('user_one_id', $otherUserId)
                            ->where('user_two_id', $currentUser->id);
                    });
            })
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $currentUser->id,
                'user_two_id' => $otherUserId,
            ]);
        }

        $conversation->load([
            'userOne',
            'userTwo',
            'serviceRequestProposal.serviceRequest',
            'serviceRequestProposal.offer',
        ]);

        return response()->json($this->resource($request, $conversation), 201);
    }

    public function forServiceRequestProposal(
        Request $request,
        ServiceRequestProposal $proposal,
    ) {
        $proposal->loadMissing(['serviceRequest', 'offer']);

        $currentUserId = $request->user()->id;
        $customerId = $proposal->serviceRequest->user_id;
        $providerId = $proposal->provider_id;

        abort_unless(
            $currentUserId === $customerId || $currentUserId === $providerId,
            403,
            'Vous ne participez pas à cette proposition.',
        );

        [$userOneId, $userTwoId] = collect([$customerId, $providerId])
            ->sort()
            ->values()
            ->all();

        $conversation = Conversation::query()->firstOrCreate([
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
            'service_request_proposal_id' => $proposal->id,
        ]);

        $conversation->load([
            'userOne',
            'userTwo',
            'serviceRequestProposal.serviceRequest',
            'serviceRequestProposal.offer',
            'messages' => function ($query) {
                $query->latest()->limit(1);
            },
        ]);

        return response()->json(
            $this->resource($request, $conversation),
            $conversation->wasRecentlyCreated ? 201 : 200,
        );
    }

    private function resource(Request $request, Conversation $conversation): array
    {
        return (new ConversationResource($conversation))->resolve($request);
    }
}
