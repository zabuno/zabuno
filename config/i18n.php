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
     * TÜRKÇE 2026-09-05'te LİSTEYE GİRDİ ve bunun tek şartı vardı: katalog
     * TAM olacak. Girdiği gün 1997 metnin sıfırı boştu.
     *
     * Buraya girmeden önce durum şuydu: Türkçe kataloğun yarısı çevrilmişti
     * (`workspace` alanında 1324 metnin 587'si boştu) ve o hâlde listeye
     * eklenseydi, ekranda "Kategori adı" ile "Build and edit the
     * categories…" yan yana dururdu. Yarım çeviri çevirisizlikten kötü
     * görünür — çünkü çevirisizlik en azından tutarlıdır.
     *
     * Kayda değer olan şu: çeviriler tamamlandığı gün bu satır
     * güncellenmedi. Yani 658 metin çevrildi ve hiçbiri kullanıcıya
     * ulaşmadı; ürün Türk restoran sahibine İngilizce konuşmaya devam etti.
     * İşi bitiren adım, işi görünür kılan adım değildir.
     *
     * ═══ VE SONRA GERİ ALINDI — SAHİBİN KARARI, 2026-09-05 ═══
     *
     * "Ben söylemedikçe tercüme çeviri yapma. İngilizce kalsın."
     *
     * Bu listenin şartı (katalog TAM olacak) bir kapıyla zorlanıyor. Türkçe
     * listede kaldığı sürece o kapı, EKLENEN HER YENİ METİN için çeviri
     * yapılmasını zorunlu kılar — yani tam olarak yapılmaması istenen iş.
     * Yeni bir metnin çevirisiz kalması kapıyı kırardı; kapıyı gevşetmek ise
     * yarım çeviriyi serbest bırakırdı ve yarım çeviri çevirisizlikten kötü
     * görünür.
     *
     * Karar bu yüzden tek yönlü uygulandı: Türkçe listeden çıktı, yeni
     * metinler İngilizce kalıyor, kapı geçiyor.
     *
     * ÇEVİRİLER SİLİNMEDİ. `lang/po/*.tr.po` içindeki 1997 metin olduğu gibi
     * duruyor ve TAMDIR; sahip istediği gün bu listeye 'tr' eklemek yeterli.
     * Silmek, bir daha yapılması gereken bir işi geri getirirdi.
     */

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
