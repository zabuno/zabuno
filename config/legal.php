<?php

declare(strict_types=1);

return [

    /*
     * HESAP VERİSİ TALEBİ — `docs/110` (P0-09), FF-169.
     *
     * Sahip "hesabımdaki her şeyi istiyorum" dediğinde talebi nereye
     * yazacağını bilmeli. Talebin YOLU üründe zaten var (`/contact`: mesajı
     * saklar, hız sınırlıdır); burada eksik olan tek şey, talebin muhatabı
     * olan ADRESTİR.
     */
    'data_request' => [

        /*
         * Talebin iletileceği adres — sahibin gireceği bir OLGU.
         *
         * BOŞ bırakılırsa sayfa bunu açıkça söyler ve hiçbir adres
         * göstermez. Buraya bir varsayılan yazmak — örnek bir e-posta ya da
         * bir posta adresi — sahibin cevap gelmeyen bir kutuya yazmasına yol
         * açardı; `config/contact.php` içindeki `notify` ile aynı karar.
         *
         * Bir SÜRE TAAHHÜDÜ burada yok ve olmamalı: "şu kadar gün içinde
         * dönülür" cümlesi hukuki bir taahhüttür ve yapılandırmadan değil,
         * sahibin hukuki incelemesinden gelir.
         */
        'address' => env('LEGAL_DATA_REQUEST_ADDRESS'),

    ],

];
