<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ServiceRequestPublished extends Notification
{
    use Queueable;

    public function __construct(public ServiceRequest $serviceRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'service_request_published',
            'title' => 'Nouvelle demande compatible',
            'message' => $this->serviceRequest->summary,
            'service_request_id' => $this->serviceRequest->id,
            'city' => $this->serviceRequest->city,
            'desired_start_at' => $this->serviceRequest->desired_start_at,
            'desired_end_at' => $this->serviceRequest->desired_end_at,
        ];
    }
}
