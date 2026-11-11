<?php

use App\Services\CommissionCalculator;

test('la commission de dix pour cent donne un net prévisible au prestataire', function () {
    config(['proxiservices.commission_rate' => 10.0]);

    expect(app(CommissionCalculator::class)->breakdown(25000))->toBe([
        'gross' => 25000.0,
        'rate' => 10.0,
        'commission' => 2500.0,
        'net' => 22500.0,
    ]);
});

test('le calcul arrondit la commission à deux décimales', function () {
    config(['proxiservices.commission_rate' => 10.0]);

    expect(app(CommissionCalculator::class)->breakdown(10.05)['commission'])->toBe(1.01);
});

test('le calcul refuse un montant brut négatif', function () {
    expect(fn () => app(CommissionCalculator::class)->breakdown(-1))
        ->toThrow(InvalidArgumentException::class);
});
