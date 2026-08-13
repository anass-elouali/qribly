<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use App\Events\MessageSent;

class MessageController extends Controller
{
    public function index(
        Request $request,
        Conversation $conversation
    ) {
        $this->authorizeConversation($request, $conversation);

        $messages = $conversation->messages()
            ->with('sender')
            ->latest()
            ->paginate(30);

        return response()->json($messages);
    }

    public function store(
        Request $request,
        Conversation $conversation
    ) {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $message->load('sender');

        MessageSent::dispatch($message);

        return response()->json($message, 201);
    }

    public function markAsRead(
        Request $request,
        Message $message
    ) {
        $conversation = $message->conversation;

        $this->authorizeConversation($request, $conversation);

        // You cannot mark your own message as read.
        if ($message->sender_id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot mark your own message as read.',
            ], 422);
        }

        $message->update([
            'read_at' => now(),
        ]);

        return response()->json($message);
    }

    private function authorizeConversation(
        Request $request,
        Conversation $conversation
    ): void {
        $userId = $request->user()->id;

        abort_unless(
            $conversation->user_one_id === $userId ||
            $conversation->user_two_id === $userId,
            403,
            'You are not a participant in this conversation.'
        );
    }
}