<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Medya kotaları — `docs/98` §7 (sahip: "sen belirle"), `docs/49` Faz 7
|--------------------------------------------------------------------------
|
| Rendition'lar kota DIŞIDIR: sistemin ürettiği türevi kullanıcıya
| ödetmeyiz. Çöp kota İÇİNDEDİR: silmek boş alan açmalı ki sahip "sildim,
| hâlâ dolu" demesin — kalıcı silme purge ile gelir.
|
| Kota dolunca CANLI MENÜ TESLİMİ KESİLMEZ; yalnız yeni yükleme durur.
|
| Plan kodları `plans.code` ile eşleşir (`starter`/`restaurant`/`team`).
| Aboneliği olmayan çalışma alanı `default`'a düşer. `null` = sınırsız.
*/
return [
    'default' => 'starter',

    'plans' => [
        'starter' => [
            'label' => 'Free',
            'original_bytes' => 200 * 1024 * 1024,
            'assets' => 100,
            'monthly_uploads' => 100,
            'trash_retention_days' => 7,
        ],
        'restaurant' => [
            'label' => 'Standart',
            'original_bytes' => 2 * 1024 * 1024 * 1024,
            'assets' => 1000,
            'monthly_uploads' => 1000,
            'trash_retention_days' => 30,
        ],
        'team' => [
            'label' => 'Pro',
            'original_bytes' => 10 * 1024 * 1024 * 1024,
            'assets' => 10000,
            'monthly_uploads' => null,
            'trash_retention_days' => 90,
        ],
    ],
];
