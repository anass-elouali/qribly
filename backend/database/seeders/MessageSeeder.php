<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->get()->keyBy('email');
        $messages = [
            ['client@qribly.test', 'prestataire@qribly.test', 'client@qribly.test', 'Bonjour Nadia, les produits de nettoyage sont-ils compris dans la prestation ?', 180, true],
            ['client@qribly.test', 'prestataire@qribly.test', 'prestataire@qribly.test', 'Bonjour Mehdi, oui, j’apporte les produits courants et le matériel nécessaire.', 165, true],
            ['client@qribly.test', 'prestataire@qribly.test', 'client@qribly.test', 'Parfait, il y aura surtout la cuisine et deux salles de bain à nettoyer.', 150, true],
            ['client@qribly.test', 'prestataire@qribly.test', 'prestataire@qribly.test', 'C’est bien noté. Je vous confirmerai l’heure exacte la veille.', 135, false],
            ['client@qribly.test', 'sara@qribly.test', 'client@qribly.test', 'Bonjour, pouvez-vous vérifier un téléphone qui ne charge plus ?', 90, true],
            ['client@qribly.test', 'sara@qribly.test', 'sara@qribly.test', 'Oui, je peux d’abord tester le connecteur et la batterie pendant le diagnostic.', 75, true],
            ['client@qribly.test', 'sara@qribly.test', 'client@qribly.test', 'Très bien, je viendrai avec le chargeur d’origine.', 60, false],
            ['lina@qribly.test', 'youssef@qribly.test', 'lina@qribly.test', 'Bonjour, est-ce que le casque est inclus avec le VTT ?', 45, true],
            ['lina@qribly.test', 'youssef@qribly.test', 'youssef@qribly.test', 'Oui, le casque et un petit kit de réparation sont inclus.', 30, true],
            ['lina@qribly.test', 'youssef@qribly.test', 'lina@qribly.test', 'Merci, je confirme alors la taille M pour vendredi.', 15, false],
        ];

        foreach ($messages as [$firstEmail, $secondEmail, $senderEmail, $body, $minutesAgo, $read]) {
            $conversation = Conversation::query()
                ->where('user_one_id', $users[$firstEmail]->id)
                ->where('user_two_id', $users[$secondEmail]->id)
                ->firstOrFail();

            $message = Message::query()->firstOrNew([
                'conversation_id' => $conversation->id,
                'sender_id' => $users[$senderEmail]->id,
                'body' => $body,
            ]);
            $timestamp = now()->subMinutes($minutesAgo);
            $message->read_at = $read ? $timestamp->addMinutes(5) : null;
            $message->created_at = $timestamp;
            $message->updated_at = $timestamp;
            $message->save();
        }
    }
}
