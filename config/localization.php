<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Çeviri üretimi kilidi
    |--------------------------------------------------------------------------
    |
    | SAHİBİN DEĞİŞTİRİLEMEZ KARARI (yönerge §1): bütün Türkçe sayfalar
    | tamamlanmadan gerçek çeviri üretimine başlanmaz. Bu bayrak yalnız sahibin
    | açık `ÇEVİRİLERE BAŞLA` talebiyle açılır; bir zamanlanmış görev, bir olay
    | dinleyicisi ya da bir ajan kendiliğinden açamaz.
    |
    | `env()` KASTEN KULLANILMIYOR. Ortam değişkeni, kilidi bir sunucu
    | yapılandırmasıyla açılabilir hâle getirirdi; oysa bu bir dağıtım ayarı
    | değil, bir ürün kararıdır ve kod incelemesinden geçmelidir.
    |
    */

    'translation_generation_enabled' => false,

];
