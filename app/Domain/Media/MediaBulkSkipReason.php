<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * KURU ÇALIŞMANIN ATLAMA SEBEPLERİ — kanonik kaynağın `skipReasons`
 * listesi (`docs/reference/panel-v3/MedyaModulu.dc.html`).
 *
 * Sebep bir KOD'dur, cümle değil: sunucu neden atladığını bilir, o sebebi
 * hangi dilde nasıl anlatacağını ürün bilir (`docs/37`). Aynı ayrım
 * `MediaFormatSupportPort::limitation()` içinde de var.
 *
 * Her sebep GERÇEK bir sorgudan doğar; hiçbiri oran/tahmin değildir.
 *
 * KAYNAKTA OLUP BURADA OLMAYAN: `perm` ("Yetkin yetmiyor · bu dosyalar
 * başka bir şubeye ait"). Bu depoda izin DOSYA başına değil ÇALIŞMA ALANI
 * başına verilir; bir kiracının içindeki dosyaların bir kısmına yetip bir
 * kısmına yetmeyen bir yetki yok. Dolayısıyla yetkisizlik bir ATLAMA değil
 * bir KAPIDIR: eylem kartı kilitlenir ve sebebi yazılır. Sahte bir "3
 * dosya yetki yüzünden atlandı" satırı, olmayan bir şube modelini varmış
 * gibi gösterirdi.
 */
enum MediaBulkSkipReason: string
{
    /** Tarama temiz dönmedi ya da dosya henüz hazır değil (`status !== ready`). */
    case Quarantine = 'quarantine';

    /** Yasal saklama kaydı var: silme ve taşıma kilitli. */
    case LegalHold = 'legal-hold';

    /** Yayınlanmış bir menü bu dosyayı gösteriyor; silinirse görsel boş kalır. */
    case PublishedUsage = 'published-usage';

    /** Bu işlem bu dosya türüne uygulanamaz (PDF/SVG yeniden boyutlandırılamaz). */
    case UnsupportedFormat = 'unsupported-format';

    /** Sonuç zaten istenen hâlde: aynı klasörde ya da zaten hedef biçimde. */
    case AlreadyDone = 'already-done';

    /** Kalıcı silme yalnız ÇÖPTEKİ dosyada çalışır; bu dosya çöpte değil. */
    case NotInTrash = 'not-in-trash';
}
