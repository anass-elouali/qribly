<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    public const PAIRS = [
        ['client@qribly.test', 'prestataire@qribly.test'],
        ['client@qribly.test', 'sara@qribly.test'],
        ['lina@qribly.test', 'youssef@qribly.test'],
    ];

    public function run(): void
    {
        $users = User::query()->get()->keyBy('email');

        foreach (self::PAIRS as [$firstEmail, $secondEmail]) {
            Conversation::firstOrCreate([
                'user_one_id' => $users[$firstEmail]->id,
                'user_two_id' => $users[$secondEmail]->id,
            ]);
        }
    }
}
