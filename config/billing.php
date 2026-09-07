<?php

/*
 * Abonelik dönemi (docs/107 Faz 1.3).
 *
 * Bir ödeme aboneliği bu kadar gün ileri taşır: varsa bitişin üstüne,
 * yoksa bugünden itibaren. İade aynı dönemi geri düşer. Sayı burada, çünkü
 * plan kataloğunda "dönem" alanı yok ve fiyatlar aylık ilan ediliyor.
 */
return [
    'subscription' => [
        'period_days' => (int) env('BILLING_SUBSCRIPTION_PERIOD_DAYS', 30),
    ],
];
