<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Domain\Authorization\Permission;

/**
 * TOPLU İŞLEMDE GERÇEKTEN YAPILABİLEN EYLEMLER — kanonik kaynak
 * `docs/reference/panel-v3/MedyaModulu.dc.html`, "Toplu işlem" bölümünün
 * `bulkActionGroups` listesi.
 *
 * ═══ KAYNAKTA OLUP BURADA OLMAYANLAR ve SEBEPLERİ ═══
 *
 * Plan kuralı açıktır (`docs/109-PANEL-V3.md` §4 madde 3): "Kaynakta olan
 * ve üründe VERİSİ OLMAYAN bir bölüm uydurulmaz." Bir toplu işlem
 * listesinde çalışmayan bir düğme, listenin en pahalı yalanıdır: sahip
 * 1.800 dosya seçer, basar ve yalnız başarısızlık toplar.
 *
 * - **`regen` (Türevleri yeniden üret)** — YOK, çünkü bu depoda
 *   `ReprocessMediaAsset` zaten tam olarak budur ve `optimize` onu
 *   çağırır. İki ayrı kart çizmek, aynı işi iki farklı isimle satmak
 *   olurdu; sahip aralarındaki farkı arar ve bulamaz.
 * - **`alt` (Alt metin öner)** — YOK, çünkü medyaya bağlı bir alt metin
 *   ÜRETİCİSİ yok. Var olan `updateAltText` tek bir metni tek bir
 *   dosyaya yazar; onu topluya çevirmek elli fotoğrafa aynı cümleyi
 *   yazmak demek olurdu ve bu, erişilebilirliği düzeltmek değil
 *   bozmaktır.
 * - **`tag` (Kategori ver)** — YOK, çünkü medya varlığının kategori/etiket
 *   kavramı bu depoda hiç yok. Klasör var, etiket yok.
 * - **`access` (Erişimi değiştir)** — YOK, çünkü `media_assets.visibility`
 *   sütunu duruyor ama HİÇBİR yerde okunmuyor: teslim yolu ona bakmıyor,
 *   CDN yok. Değiştiren bir düğme, hiçbir şeyi değiştirmeyen bir düğme
 *   olurdu — ve sahip "özele aldım" diyerek yanlış bir güven duyardı.
 * - **`archive` (Arşive taşı)** — YOK, çünkü `LifecycleStatus::Archived`
 *   tanımlı ama hiçbir listeleme ondan süzmüyor: arşive alınan dosya
 *   kütüphanede aynen durmaya devam ederdi.
 *
 * Beşi de kaynağa geri dönebilir; her biri kendi veri katmanını
 * getirdiğinde. Bugün listede olmamaları bir eksiklik değil, listede
 * olanların gerçekten çalıştığının teminatıdır.
 */
enum MediaBulkAction: string
{
    /** Var olan tek-varlık yeniden üretimi, seçili dosyalar için sırayla. */
    case Optimize = 'optimize';

    /** Var olan `ConvertMediaAssetsToFormat` — hedef biçim `config.format`. */
    case Convert = 'convert';

    /** Var olan klasör taşıma — dosyaya dokunmaz, yalnız nerede aranacağı değişir. */
    case Move = 'move';

    /** Var olan çöpe atma: dosya diskte kalır, geri alınabilir. */
    case Trash = 'trash';

    /** Çöpteki dosyayı KALICI siler — dosya, türevleri ve satırı gider. */
    case Purge = 'purge';

    /**
     * Bu eylemi yapmak için gereken izin.
     *
     * Kaynağın dört kademeli rol modeli (izleyici/editör/yönetici/sahip)
     * bu deponun rol modeli DEĞİLDİR ve uydurulmaz. Bu depoda `media.manage`
     * editörde de vardır — görsel yüklemek içerik düzenlemektir. Kalıcı
     * silme ise `workspace.manage` ister: kaynağın "yalnız sahip" kuralının
     * bu depodaki gerçek karşılığı budur, çünkü `workspace.manage` yalnız
     * sahip ve yöneticidedir (`RolePermissions`).
     */
    public function requiredPermission(): Permission
    {
        return match ($this) {
            self::Purge => Permission::WorkspaceManage,
            default => Permission::MediaManage,
        };
    }

    /**
     * Geri alınabilir mi? Kaynağın kart etiketi ("Geri alınabilir" /
     * "Geri alınamaz") buradan gelir, ekrandan değil.
     */
    public function isReversible(): bool
    {
        return $this !== self::Purge;
    }

    /**
     * Yıkıcı mı? Yıkıcı işlem, kaynağın kuralı gereği onay kutusuyla
     * değil YAZILI onayla çalışır.
     */
    public function isDestructive(): bool
    {
        return $this === self::Trash || $this === self::Purge;
    }

    /**
     * Onay için yazılacak kelime. Kaynağın kendi ayrımı: kalıcı silme
     * `KALICI SİL`, diğer büyük kapsamlar `ONAYLA`.
     */
    public function confirmWord(): string
    {
        return $this === self::Purge ? 'KALICI SİL' : 'ONAYLA';
    }

    /**
     * Bu eylem ÇÖPTEKİ dosyalar üzerinde mi çalışır?
     *
     * Yalnız kalıcı silme öyledir ve bu bir kısıt değil, kaynağın "30 gün
     * çöpte bekler" kuralının korunmasıdır: çöpe uğramadan kalıcı silmek,
     * geri alma penceresini tamamen ortadan kaldırırdı.
     */
    public function operatesOnTrash(): bool
    {
        return $this === self::Purge;
    }
}
