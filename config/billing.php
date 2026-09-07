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

    /*
     * FATURA (docs/107 Faz 1.4, docs/130).
     *
     * İki alan da VARSAYILANSIZDIR ve bu bilinçlidir.
     */
    'invoice' => [

        /*
         * Belge numarasının önüne yazılacak seri harfleri.
         *
         * BOŞ bırakılırsa numara `2026-000001` biçiminde çıkar. Buraya bir
         * varsayılan yazmak — üç harfli uydurma bir seri kodu — GİB'in
         * e-arşiv seri biçimini taklit ederdi ve belge, olmadığı bir şeye
         * benzerdi. Seri harfleri ancak sahibin muhasebecisi bir seri
         * belirlediğinde girilir.
         */
        'number_prefix' => env('BILLING_INVOICE_NUMBER_PREFIX'),

        /*
         * KDV oranı, ONBİNDE (basis point): %20 → 2000.
         *
         * VARSAYILAN YOK ve olmayacak. Bu depoda ölçülmüş bir oran kaynağı
         * yok; bildiğimizi sandığımız bir oranı gömmek, belgeye yanlış bir
         * vergi tutarı yazdırmanın en sessiz yoludur.
         *
         * Boşken belge yalnız TAHSİL EDİLEN tutarı gösterir ve KDV ayrımının
         * yapılandırılmadığını söyler. Doldurulduğunda sahip aynı zamanda şunu
         * BEYAN ETMİŞ olur: plan fiyatları bu oranda KDV DAHİLDİR. Ayrım o
         * beyandan türetilir ve belgeye o günkü oranıyla mühürlenir; sonradan
         * oran değişse bile kesilmiş belge değişmez.
         */
        'vat_rate_basis_points' => env('BILLING_INVOICE_VAT_RATE_BASIS_POINTS'),

    ],
];
