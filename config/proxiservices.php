<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Commission de la plateforme
    |--------------------------------------------------------------------------
    |
    | Pourcentage prélevé théoriquement sur le prix convenu.
    | La valeur de 10 % est un exemple initial à confirmer.
    |
    */

    'commission_rate' => (float) env(
        'PROXISERVICES_COMMISSION_RATE',
        10.0,
    ),
];
