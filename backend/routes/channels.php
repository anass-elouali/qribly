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