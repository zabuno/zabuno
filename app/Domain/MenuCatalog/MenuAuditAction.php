<?php

declare(strict_types=1);

namespace App\Domain\MenuCatalog;

/**
 * İZE YAZILAN OLAYLARIN TAM LİSTESİ — FF-154, FF-156.
 *
 * MENÜYE YAZAN YENİ BİR YOL EKLİYORSAN ÖNCE BURAYI OKU. Bu liste izin
 * SÖZLEŞMESİDİR: bir yol menüye yazıp ize yazmazsa, iz eksildiğini
 * söylemez — TAM görünür. Sahip izi açar, aradığı fiyat değişikliğini
 * bulamaz ve "demek kimse dokunmamış" der. Yeni yolun ya buradaki bir olayı
 * yazması ya da aşağıdaki "yazılmayanlar" listesine gerekçesiyle girmesi
 * gerekir; ikisini de yapmayan bir yol
 * `Tests\Unit\Architecture\MenuWritePathsAreAuditedTest`'i kırar.
 *
 * Liste bir ÖLÇÜTLE seçildi: *sahip bunu sorar mı?* Her şeyi kaydetmek izi
 * gürültüye çevirir ve fiyat sorusunu 200 satırın altına gömer; hiçbir şey
 * kaydetmemek bugünkü durumdur. Ölçütün pratik karşılığı şu: bir olay ya
 * MİSAFİRİN GÖRDÜĞÜNÜ değiştirir (fiyat, ad, görünürlük, alerjen) ya da bir
 * şeyin VARLIĞINI değiştirir (ekleme, silme).
 *
 * İZE YAZILMAYANLAR ve sebepleri — bu liste kadar bağlayıcıdır:
 *
 * - **Sıralama** (`item-order`, `category-order`): sıra misafire verilmiş
 *   bir söz değildir ve menü düzenlenirken onlarca kez değişir. "Çorbayı
 *   kim yukarı aldı?" diye soran bir sahip yok.
 * - **"Bugün bitti"** (`stock`): servis sırasında mutfağın attığı, ertesi
 *   gün kendiliğinden silinen bir tebeşir notudur — ve sistemdeki EN SIK
 *   mutasyondur. Yazılsaydı iz, akşam servisinin kayıtlarıyla dolardı.
 * - **Yayınlama**: ZATEN kayıtlı. `menu_publications` her yayının failini
 *   (`published_by`) ve anını (`published_at`) tutuyor ve çalışma alanı
 *   zaman çizgisi (`EloquentWorkspaceAuditTrail`) bunu zaten okuyor. İkinci
 *   kez yazmak, aynı olgunun iki farklı sayıda görünmesine yol açardı.
 * - **Servis aralığı** (`service-window`): menünün günün hangi saatinde
 *   açılacağı bir yerleşim kararıdır, menünün İÇERİĞİ değil.
 * - **Fotoğraf bağlama**: görselin kendi izi `media_audits`tedir.
 * - **Ürün açıklaması** (elle düzenleme ve AI önerisinin onayı, FF-156):
 *   açıklama pazarlama metnidir, sahibin "kim değiştirdi" diye sorduğu şey
 *   değil. Kural KİM DEĞİŞTİRİRSE DEĞİŞTİRSİN aynıdır: AI yolunu
 *   (`ApplyProductDescriptionDraft`) kaydedip elle düzenlemeyi
 *   (`RenameMenuItemController`) kaydetmemek izi TERS yönde yanıltırdı —
 *   sahip yalnız makinenin dokunduğu metinleri görür, aynı cümleyi elle
 *   değiştiren editörü göremezdi.
 */
enum MenuAuditAction: string
{
    /** Şubeye yeni bir menü açıldı. */
    case MenuCreated = 'menu_created';

    /** Menünün adı — ekrandaki hapın üstünde okunan şey — değişti. */
    case MenuRenamed = 'menu_renamed';

    /** Menü ve içindeki her şey silindi: tek tıkla en yıkıcı işlem. */
    case MenuDeleted = 'menu_deleted';

    /**
     * CSV aktarımı menüye toplu yazdı.
     *
     * Aktarım, menünün her fiyatını TEK dosyayla değiştirebilen yoldur;
     * izsiz bırakılsaydı "fiyatı kim değiştirdi" sorusu bir kaçış yolu
     * bulurdu. Satır başına kayıt ise 60 kalemlik bir menüde izi tek başına
     * doldururdu — bu yüzden tek bir ÖZET satırı yazılır.
     */
    case MenuImported = 'menu_imported';

    /**
     * FOTOĞRAFTAN okunan bir taslağın onayı menüye toplu yazdı — FF-156.
     *
     * NEDEN CSV'DEN AYRI BİR OLAY? İkisi de "aktarım"dır ama aynı
     * güvenilirlik iddiası değildir: CSV'deki fiyatı sahip YAZDI,
     * fotoğraftaki fiyatı bir model OKUDU. Sahip yanlış bir fiyat bulduğunda
     * ihtiyacı olan ayrım tam budur — sayı yanlış yazılmış olabileceği gibi
     * yanlış OKUNMUŞ da olabilir. Ayrımı özet metninin içine gömseydik, izi
     * okuyan her ekranı bir gün metin ayrıştırmaya zorlardık.
     *
     * FAİL "AI" DEĞİLDİR. Menüye yazma kararını makine vermez; onay
     * düğmesine basan insan verir (`docs/97` R4 — "o önerir, siz
     * onaylarsınız"). `actor_user_id` o kişidir; makinenin payı eylemin
     * KENDİSİNDE durur.
     *
     * TASLAK BAŞINA TEK ÖZET SATIRI — CSV'yle aynı ölçü: bir KAYNAK BELGE
     * bir satırdır. Toplu onay en çok on taslak alır, yani satır sayısını
     * menünün büyüklüğü değil, sahibin gözden geçirdiği belge sayısı
     * belirler.
     */
    case MenuAiImported = 'menu_ai_imported';

    case CategoryAdded = 'category_added';

    case CategoryRenamed = 'category_renamed';

    /** Kategoriyi silmek İÇİNDEKİ HER SATIRI götürür (`cascade`). */
    case CategoryRemoved = 'category_removed';

    case ItemAdded = 'item_added';

    /** Ürün adı: misafirin menüde okuduğu metin. */
    case ItemRenamed = 'item_renamed';

    /** Paketin var oluş sebebi: "dün kebabın fiyatını kim değiştirdi?" */
    case ItemPriceChanged = 'item_price_changed';

    case ItemVisibilityChanged = 'item_visibility_changed';

    /** Alerjen YASAL SORUMLULUKTUR; kaldıran kişi bilinmek zorundadır. */
    case ItemAllergensChanged = 'item_allergens_changed';

    case ItemRemoved = 'item_removed';
}
