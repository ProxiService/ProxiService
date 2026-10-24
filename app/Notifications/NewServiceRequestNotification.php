<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class NewServiceRequestNotification extends Notification
{
    public function __construct(
        public int $serviceRequestId,
        public string $serviceName,
        public string $clientName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'service_request_received',
            'title' => 'Nouvelle demande reçue',
            'message' => "{$this->clientName} a envoyé une demande pour {$this->serviceName}.",
            'service_request_id' => $this->serviceRequestId,
            'url' => route('prestataire.requests.index'),
        ];
    }
}
