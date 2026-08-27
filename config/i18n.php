<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Yayınlanan arayüz dilleri
    |--------------------------------------------------------------------------
    |
    | Kullanıcıya SUNULAN dillerin tek kaynağı. Katalogların derlenmiş
    | olması bir dilin sunulduğu anlamına gelmez: bugün altı katalog
    | derleniyor ama yalnız bu listedeki diller ürüne girer.
    |
    | Owner kararı (2026-08-27): şimdilik yalnız İngilizce. Çeviriyi sahibi
    | olgunluk sonrasında PO dosyalarından kendisi yazacak (`docs/13` §6) ve
    | bir dil, kataloğu tamamlanmadan sunulmaz.
    |
    | Sebebi gözle görüldü: uygulama `APP_LOCALE=tr` ile çalışırken `menu`
    | alanı çevriliydi, `workspace` alanı değildi. Ekranda "Kategori adı"
    | ile "Build and edit the categories…" yan yana duruyordu. Yarım çeviri,
    | çevirisizlikten kötü görünür — çünkü çevirisizlik tutarlıdır.
    |
    | Bu listeye bir dil eklemek, o dilin kataloğunun TAM olmasını gerektirir;
    | `ShippedLocalesAreCompleteTest` bunu zorlar.
    |
    */

    'shipped_locales' => ['en'],

    /*
    |--------------------------------------------------------------------------
    | Kaynak dil
    |--------------------------------------------------------------------------
    |
    | Kodda yazılan her dize bu dildedir ve bu katalog her zaman eksiksiz
    | olmak zorundadır (`I18N-SIX-CATALOGS-10`).
    |
    */

    'source_locale' => 'en',

];
