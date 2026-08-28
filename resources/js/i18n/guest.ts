/**
 * MİSAFİR YÜZEYİ kataloğu — masadaki karekodu okutan kişinin okuduğu metin.
 *
 * KAYNAK DİL TÜRKÇE. Diğer kataloglar İngilizce kaynaklıdır ve bu bilinçli
 * bir ayrım:
 *
 *   - Panel metinleri ÜRÜNÜN dilidir; ürün İngilizce kaynaktan çevrilir.
 *   - Misafir sayfası RESTORANIN dilidir. Ürün adlarını restoran kendi
 *     dilinde yazar (`contentLocale`), ve bu sayfadaki her metin bugün
 *     zaten Türkçedir.
 *
 * Kaynağı İngilizce yapmak, çeviri dosyası doldurulana kadar Türk bir
 * restoranın menüsünde İngilizce bir cümle gösterirdi — var olmayan bir
 * sorunu çözmek için gerçek bir gerileme.
 *
 * Bu katalog `docs/82` ile AÇILDI ve `docs/xx` (P1-06, misafir dil seçimi)
 * ile misafir sayfasının tamamını devralacak. Bugün tek anahtar taşıyor.
 */
export const guestTranslations = {
    // Tükendi METİNLE söylenir; yalnız renk ya da soluklukla anlatmak, renk
    // göremeyen misafir için hiçbir şey anlatmaz (WCAG 1.4.1).
    'guest.menu.item.soldOut': 'Bugün tükendi',
} as const;

export type GuestTranslationKey = keyof typeof guestTranslations;

export default guestTranslations;
