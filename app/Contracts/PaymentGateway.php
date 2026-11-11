<?php

namespace App\Contracts;

use App\Models\ServicePayment;
use App\Models\ServiceRequest;
use App\Models\User;

/** Contrat limité aux opérations dont GeniusPay confirme le support. */
interface PaymentGateway
{
    /** @return array<string, mixed> */
    public function createCheckout(
        ServicePayment $payment,
        ServiceRequest $serviceRequest,
        User $customer,
    ): array;
}
