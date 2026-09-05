<?php

declare(strict_types=1);

namespace App\Domain\MenuCatalog;

/**
 * İZE YAZILAN OLAYLARIN TAM LİSTESİ — FF-154.
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
