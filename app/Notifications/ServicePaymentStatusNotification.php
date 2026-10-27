<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ServicePaymentStatusNotification extends Notification
{
    public function __construct(
        public int $paymentId,
        public int $serviceRequestId,
        public string $serviceName,
        public float $amount,
        public string $currency,
        public string $paymentStatus,
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
        $currencyLabel = $this->currency === 'XOF'
            ? 'FCFA'
            : $this->currency;

        $amountLabel = number_format(
            $this->amount,
            0,
            ',',
            ' ',
        ).' '.$currencyLabel;

        [$title, $message] = match ($this->paymentStatus) {
            'completed' => $this->recipientRole === 'prestataire'
                ? [
                    'Paiement confirmé',
                    "Le paiement de {$amountLabel} pour la prestation « {$this->serviceName} » a été confirmé par GeniusPay.",
                ]
                : [
                    'Paiement confirmé',
                    "Ton paiement de {$amountLabel} pour « {$this->serviceName} » a été confirmé par GeniusPay.",
                ],

            'failed' => [
                'Paiement échoué',
                "Le paiement de {$amountLabel} pour « {$this->serviceName} » n’a pas abouti. Tu peux consulter ta demande pour vérifier la situation.",
            ],

            'cancelled' => [
                'Paiement annulé',
                "La tentative de paiement de {$amountLabel} pour « {$this->serviceName} » a été annulée.",
            ],

            'expired' => [
                'Paiement expiré',
                "La tentative de paiement de {$amountLabel} pour « {$this->serviceName} » a expiré.",
            ],

            'refunded' => [
                'Paiement remboursé',
                "GeniusPay a signalé le remboursement du paiement de {$amountLabel} pour « {$this->serviceName} ». Vérifie les détails de ta demande.",
            ],

            default => [
                'Mise à jour du paiement',
                "Le statut du paiement de {$amountLabel} pour « {$this->serviceName} » a changé.",
            ],
        };

        $url = match ($this->recipientRole) {
            'client' => route('client.requests.index'),
            'prestataire' => route('prestataire.requests.index'),
            default => route('dashboard'),
        };

        return [
            'type' => 'service_payment_'.$this->paymentStatus,
            'title' => $title,
            'message' => $message,
            'payment_id' => $this->paymentId,
            'service_request_id' => $this->serviceRequestId,
            'service_name' => $this->serviceName,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->paymentStatus,
            'url' => $url,
        ];
    }
}
