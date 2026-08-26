/**
 * Menü kataloğunun Türkçe çevirisi.
 *
 * Zabuno'nun asıl kitlesi Türk restoranlarıdır, bu yüzden menü yüzeyi
 * altı katalogdan ilk tamamlananıdır. Kalan diller (de/fr/ar/ru) Stage 1'de
 * scaffold'dur ve `en`'e düşer — bu bir eksik değil, `docs/26` S1-WP03'te
 * kayıtlı bir kapsam kararıdır.
 *
 * Anahtar tabanda yoksa TypeScript burada hata verir; ölü anahtar birikemez.
 */
export const menuTr = {
    'menu.loading': 'Menü yükleniyor…',
    'menu.status.saving': 'Kaydediliyor…',
    'menu.initial.error.load': 'Menünüzü yükleyemedik. Lütfen tekrar deneyin.',
    'menu.initial.error.retry': 'Tekrar dene',
    'menu.name.label': 'Menü adı',
    'menu.name.error.required': 'Bir menü adı girin.',
    'menu.create.submit': 'Menü oluştur',
    'menu.create.error.submit': 'Menüyü oluşturamadık. Lütfen tekrar deneyin.',
    'menu.categories.list.label': 'Menü kategorileri',
    'menu.category.select.label': 'Kategori',
    'menu.category.name.label': 'Kategori adı',
    'menu.category.create.submit': 'Kategori ekle',
    'menu.category.name.error.required': 'Bir kategori adı girin.',
    'menu.category.create.error.submit': 'Kategoriyi ekleyemedik. Lütfen tekrar deneyin.',
    'menu.category.items.label': '{name} içindeki ürünler',
    'menu.product.name.label': 'Ürün adı',
    'menu.product.create.submit': 'Ürün ekle',
    'menu.product.name.error.required': 'Bir ürün adı girin.',
    'menu.product.create.error.submit': 'Ürünü ekleyemedik. Lütfen tekrar deneyin.',
    'menu.item.price.label': 'Fiyat',
    'menu.item.currency.label': 'Para birimi',
    'menu.item.create.submit': 'Kalem ekle',
    'menu.item.price.error.required': 'Bir fiyat girin.',
    'menu.item.create.error.submit': 'Menü kalemini ekleyemedik. Lütfen tekrar deneyin.',
    'menu.item.allergens.label': 'Alerjenler (virgülle ayrılmış)',
    'menu.item.allergens.submit': 'Alerjenleri kaydet',
    'menu.item.allergens.error.submit': 'Alerjenleri güncelleyemedik. Lütfen tekrar deneyin.',
    'menu.item.allergens.list.label': '{name} alerjenleri',
    'menu.item.allergens.edit.button': '{name} alerjenlerini düzenle',
    'menu.item.price.edit.button': '{name} fiyatını düzenle',
    'menu.item.price.edit.submit': 'Fiyatı kaydet',
    'menu.item.price.edit.error.submit': 'Fiyatı güncelleyemedik. Lütfen tekrar deneyin.',
    'menu.item.visibility.checkbox.label': '{name} ürününü göster',
    'menu.item.visibility.error.submit': 'Görünürlüğü güncelleyemedik. Lütfen tekrar deneyin.',
    'menu.category.order.label': '{name} sırası',
    'menu.item.order.label': '{name} sırası',
} as const;
