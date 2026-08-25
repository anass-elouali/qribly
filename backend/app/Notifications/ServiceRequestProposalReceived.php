<?php

namespace App\Notifications;

use App\Models\ServiceRequestProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ServiceRequestProposalReceived extends Notification
{
    use Queueable;

    public function __construct(public ServiceRequestProposal $proposal) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'service_request_proposal_received',
            'title' => 'Nouvelle proposition',
            'message' => 'Un prestataire a répondu à ta demande.',
            'service_request_id' => $this->proposal->service_request_id,
            'proposal_id' => $this->proposal->id,
            'provider_id' => $this->proposal->provider_id,
            'proposed_price' => $this->proposal->proposed_price,
            'scheduled_at' => $this->proposal->scheduled_at,
        ];
    }
}
