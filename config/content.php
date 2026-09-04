<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sayfa kapısının ortamı
    |--------------------------------------------------------------------------
    |
    | VARSAYILAN PRODUCTION'DIR ve bu bilinçlidir (FF-117). Ortamı Laravel'in
    | `APP_ENV` değerinden türetmek, yerelde ve testte "staging" davranışı
    | üretir — yani taslakların 200 ile sunulması. Yanlış tarafta hata yapmak
    | istemiyoruz: yapılandırması unutulmuş bir sunucu, taslakları herkese
    | açan bir sunucu olmamalı.
    |
    | Staging AÇIKÇA seçilir. O ortamda her sayfa görünür ve hiçbir sayfa
    | indekslenmez; ekip sayfaları gezerek kontrol eder.
    |
    */

    'page_environment' => env('PAGE_ENVIRONMENT', 'production'),

];
