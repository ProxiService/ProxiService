<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ServiceRequestStatusNotification extends Notification
{
    public function __construct(
        public int $serviceRequestId,
        public string $serviceName,
        public string $status,
        public string $actorName,
        public string $recipientRole,
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
        [$title, $message] = match ($this->status) {
            'accepted' => [
                'Demande acceptée',
                "{$this->actorName} a accepté ta demande pour « {$this->serviceName} ».",
            ],

            'rejected' => [
                'Demande refusée',
                "{$this->actorName} a refusé ta demande pour « {$this->serviceName} ».",
            ],

            'in_progress' => [
                'Intervention démarrée',
                "Le prestataire {$this->actorName} a commencé l’intervention pour « {$this->serviceName} ».",
            ],

            'awaiting_confirmation' => [
                'Fin de prestation à confirmer',
                "Le prestataire {$this->actorName} indique que l’intervention « {$this->serviceName} » est terminée. Vérifie le travail avant de confirmer.",
            ],

            'completed' => [
                'Prestation terminée',
                "Le client {$this->actorName} a confirmé que la prestation « {$this->serviceName} » est terminée.",
            ],

            default => [
                'Mise à jour de la demande',
                "Le statut de la demande pour « {$this->serviceName} » a changé.",
            ],
        };

        $url = match ($this->recipientRole) {
            'client' => route('client.requests.index'),
            'prestataire' => route('prestataire.requests.index'),
            default => route('dashboard'),
        };

        return [
            'type' => 'service_request_'.$this->status,
            'title' => $title,
            'message' => $message,
            'service_request_id' => $this->serviceRequestId,
            'service_name' => $this->serviceName,
            'status' => $this->status,
            'actor_name' => $this->actorName,
            'url' => $url,
        ];
    }
}
