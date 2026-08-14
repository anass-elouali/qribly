<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversation}', function (
    User $user,
    Conversation $conversation
) {
    return $conversation->user_one_id === $user->id
        || $conversation->user_two_id === $user->id;
});

// Per-user inbox channel: lets clients learn about new messages (to update
// unread badges, conversation previews) without subscribing to every
// conversation channel individually.
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});