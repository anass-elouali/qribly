<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
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
                'messages' => function ($query) {
                    $query->latest()->limit(1);
                },
            ])
            ->latest('updated_at')
            ->get();

        return response()->json($conversations);
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
            ->where(function ($query) use ($currentUser, $otherUserId) {
                $query->where('user_one_id', $currentUser->id)
                    ->where('user_two_id', $otherUserId);
            })
            ->orWhere(function ($query) use ($currentUser, $otherUserId) {
                $query->where('user_one_id', $otherUserId)
                    ->where('user_two_id', $currentUser->id);
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
        ]);

        return response()->json($conversation, 201);
    }
}