<?php

declare(strict_types=1);

/**
 * Derleme kimliği sözleşmesi (docs/52 — Preview Truth).
 *
 * `revision` deploy sırasında ZABUNO_BUILD_REVISION ile verilir. Verilmezse
 * sürüm `.git`'ten okunur; bu geliştirmede doğru davranıştır ama üretimde
 * güvenilemez, çünkü deploy edilen dizin bir git çalışma kopyası olmayabilir.
 *
 * `banner` uyarı şeridini kapatır. Varsayılan olarak yalnız üretim DIŞINDA
 * açıktır: bayat derleme bir geliştirme döngüsü kusurudur ve restoran
 * sahibine gösterilecek bir şey değildir. Üretimde sürüm uyuşmazlığı hâlâ
 * ölçüme gider — sessizleştirilen şey ekran, kayıt değil.
 */
return [
    'revision' => env('ZABUNO_BUILD_REVISION'),

    // `app()->environment()` BURADA ÇAĞRILAMAZ: yapılandırma dosyaları
    // konteyner tam kurulmadan okunur ve orada `env` bağlaması henüz yoktur.
    // Ortam adı doğrudan okunur.
    'banner' => (bool) env('ZABUNO_BUILD_BANNER', env('APP_ENV', 'production') !== 'production'),
];
