<?php

declare(strict_types=1);

namespace App\Support\Localization;

/**
 * Dil TÜRÜ — `docs/120` §4.1.
 *
 * Bu depoda ölçülen kusur tam olarak buydu (`docs/118` E4): kurumsal sayfa
 * dilini ADRESTEN, ürün arayüzü TARAYICIYLA PAZARLIKTAN alıyordu. İkisi de
 * doğruydu ama ayrım hiçbir yerde YAZILI DEĞİLDİ — yani kazayla doğruydu.
 * Kazayla doğru olan bir şey bir gün kazayla yanlış olur.
 *
 * Bu enum o ayrımın adıdır. Bir kullanıcının arayüzü İngilizce, okuduğu
 * sayfa Türkçe olabilir ve bu bir hata değildir: `/tr/urun/qr-menu/` Türkçe
 * YAZILMIŞ bir sayfadır ve tarayıcı ayarı onu İngilizceye çeviremez.
 */
enum LanguageType: string
{
    /** Düğme, etiket, hata mesajı — ürünün kendi metni. */
    case Interface = 'interface';

    /** Kurumsal sayfanın, blog yazısının, menünün metni. Pazarlık YOK. */
    case Content = 'content';

    /** Adresin hangi dil dizininde olduğu. */
    case Url = 'url';
}
