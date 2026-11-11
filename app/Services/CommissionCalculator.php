<?php

namespace App\Services;

use InvalidArgumentException;

class CommissionCalculator
{
    /** @return array{gross: float, rate: float, commission: float, net: float} */
    public function breakdown(float $gross): array
    {
        if (! is_finite($gross) || $gross < 0) {
            throw new InvalidArgumentException('Le montant brut doit être positif.');
        }

        $rate = (float) config('proxiservices.commission_rate', 10.0);
        if (! is_finite($rate) || $rate < 0 || $rate > 100) {
            throw new InvalidArgumentException('Le taux de commission configuré est invalide.');
        }

        $commission = round($gross * $rate / 100, 2);

        return [
            'gross' => round($gross, 2),
            'rate' => $rate,
            'commission' => $commission,
            'net' => round($gross - $commission, 2),
        ];
    }
}
