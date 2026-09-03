import { createTranslator } from './translator';
import { overridesFor } from './generated-overrides';

const en = {
    'menu.loading': 'Loading menu…',
    'menu.status.saving': 'Saving…',
    'menu.initial.error.load': 'We could not load your menu. Please try again.',
    'menu.initial.error.retry': 'Retry',
    'menu.name.label': 'Menu name',
    'menu.name.error.required': 'Enter a menu name.',
    'menu.create.submit': 'Create menu',
    'menu.create.error.submit': 'We could not create the menu. Please try again.',
    'menu.categories.list.label': 'Menu categories',
    'menu.category.select.label': 'Category',
    'menu.category.name.label': 'Category name',
    'menu.category.create.submit': 'Add category',
    'menu.category.name.error.required': 'Enter a category name.',
    'menu.category.create.error.submit': 'We could not add the category. Please try again.',
    'menu.category.items.label': 'Items in {name}',
    'menu.category.add.disclose': 'Add category',
    'menu.category.add.cancel': 'Cancel',
    'menu.product.name.label': 'Product name',
    'menu.product.create.submit': 'Add product',
    'menu.product.name.error.required': 'Enter a product name.',
    'menu.product.create.error.submit': 'We could not add the product. Please try again.',
    // Menüye ürün eklemenin TEK adımı. Öncesinde bu iş üç ayrı formdu:
    // ürün, fiyat, alerjen. Kullanıcı için hepsi tek bir iştir.
    // Ürün ekleme artık KATEGORİNİN İÇİNDE. Kategori bir alan değil,
    // tıkladığın yerdir — sahibinin tespitinin bir adım ötesi.
    'menu.entry.section.label': 'Add a product to {category}',
    'menu.entry.open': 'Add product',
    'menu.entry.cancel': 'Cancel',
    'menu.category.empty': 'No products in this category yet.',
    'menu.entry.category.label': 'Category',
    'menu.entry.category.empty': 'Add a category first — every product lives in one.',
    'menu.entry.allergens.disclose': 'Allergens (optional)',
    'menu.entry.submit': 'Add to menu',
    'menu.entry.error.submit': 'We could not add the product. Please try again.',
    'menu.entry.success': '{name} was added to {category}.',
    'menu.item.price.label': 'Price',
    'menu.item.currency.label': 'Currency',
    'menu.item.create.submit': 'Add item',
    'menu.item.price.error.required': 'Enter a price.',
    'menu.item.create.error.submit': 'We could not add the menu item. Please try again.',
    'menu.item.allergens.label': 'Allergens (comma-separated)',
    'menu.item.allergens.submit': 'Save allergens',
    'menu.item.allergens.error.submit': 'We could not update allergens. Please try again.',
    'menu.item.allergens.list.label': 'Allergens for {name}',
    // Görünen metin KISA, erişilebilir isim TAM. Ürün adını her butonun
    // içine basmak satırı okunamaz hâle getiriyordu ("Mercimek Çorbası
    // alerjenlerini düzenle" × her satır); ekran okuyucunun ihtiyaç
    // duyduğu bağlam ise `aria-label`'da korunur.
    'menu.item.allergens.edit.button': 'Edit allergens for {name}',
    'menu.item.price.edit.button': 'Edit price for {name}',
    'menu.item.allergens.edit.short': 'Allergens',
    'menu.item.price.edit.short': 'Price',
    // "Bugün tükendi" (`docs/82`) — GÖRÜNÜRLÜKTEN ayrı bir eksen. Gizli bir
    // ürün menüde yoktur; tükenmiş bir ürün menüde vardır ama bugün alınamaz.
    'menu.item.stock.out.short': 'Sold out',
    'menu.item.stock.back.short': 'Back in stock',
    'menu.item.stock.out.button': 'Mark {name} sold out for today',
    'menu.item.stock.back.button': 'Mark {name} available again',
    // KATEGORİ GENELİ (`docs/82` kriter 3, ekranı `docs/98` FF-64): "balıklar
    // bitti" altı ayrı tıklama olmamalı.
    'menu.category.stock.out.short': 'All sold out',
    'menu.category.stock.back.short': 'All available',
    'menu.category.stock.out.button': 'Mark everything in {name} sold out for today',
    'menu.category.stock.back.button': 'Mark everything in {name} available again',
    // Menüyü ALMAK ve GERİ KOYMAK (`docs/80`).
    // `docs/101` A5/A8 (FF-73): içe aktarma TEK kutuda; boş menüde açık.
    'menu.tools.summary': 'Bring in a whole menu',
    'menu.tools.help': 'From a photo of your printed menu, or from a CSV file.',
    'menu.empty.guide':
        'Start here: bring in your whole menu from a photo or a CSV below, or add a category and a product one at a time.',
    'menu.export.download': 'Download menu (CSV)',
    'menu.import.label': 'Import a CSV menu',
    'menu.import.help':
        'Columns: category, product, price, currency, allergens, description, visible. Nothing reaches guests until you publish.',
    'menu.import.done': 'Imported {items} items into {categories} new categories.',
    'menu.import.rejected': '{count} rows could not be read:',
    'menu.import.rejected.row': 'Line {line}: {reason}',
    'menu.import.error': 'The file could not be imported.',
    // SUNUM: açıklama ve fotoğraf tek düzenleyicide. Sahibin yaptığı iş
    // tektir — "bu ürünü misafire nasıl göstereceğim" — ve iki ayrı düğme
    // aynı satır için iki kez form açtırırdı.
    'menu.item.presentation.edit.short': 'Photo & text',
    'menu.item.presentation.edit.button': 'Edit photo and description for {name}',
    'menu.item.presentation.submit': 'Save presentation',
    'menu.item.presentation.error.image':
        'The description was saved, but the photo was not attached.',
    'menu.item.description.label': 'Description',
    'menu.item.description.help':
        'A short line the guest reads under the name. Up to 500 characters.',
    // AI önerisi — `docs/97` Yolculuk B. Öneri bir taslaktır: kutuya
    // otomatik yazılır ama SAHİP onaylamadan (Kaydet'e basmadan) ürüne
    // geçmez; onaylamadan önce serbestçe düzenleyebilir.
    'menu.item.ai.description.request': 'Suggest with AI',
    'menu.item.ai.description.loading': 'Asking AI…',
    'menu.item.ai.description.suggested': 'AI suggestion — feel free to edit it before saving.',
    'menu.item.ai.description.suggested.fallback':
        'AI suggestion (from a backup provider) — feel free to edit it before saving.',
    'menu.item.ai.description.uncertain':
        'The AI was not confident about this one. Read it carefully before saving.',
    'menu.item.ai.description.unavailable': 'AI suggestions are not available right now.',
    'menu.item.ai.description.error': 'The AI suggestion failed. You can still write one yourself.',
    // AI KULLANILAMIYORSA — `docs/97` R9 / AIV-07. Eylem gösterilmez, YERİNE
    // sebebi yazılır: üç sebep üç FARKLI çözüme işaret eder (yönetici açar /
    // ay biter ya da bütçe artırılır / sağlayıcı anahtarı girilir). Tek bir
    // "kullanılamıyor" metni, sahibi kime gideceğini bilmeden bırakırdı.
    'menu.ai.unavailable.kill_switch': 'AI help is turned off right now.',
    'menu.ai.unavailable.budget_exhausted':
        'This month’s AI budget is used up. Everything else keeps working.',
    'menu.ai.unavailable.no_route': 'No AI provider is set up yet.',
    // OLASI TEKRARLAR — `docs/97` Yolculuk C. Salt okunur bir öneridir;
    // bir "birleştir" eylemi kasıtlı olarak yok (`docs/96` Faz 2 kapsamı).
    'menu.duplicates.heading': 'Possible duplicates ({count})',
    'menu.duplicates.help':
        'These product names look alike. Nothing is merged automatically — rename or remove one yourself if they really are the same.',
    'menu.duplicates.pair': '“{a}” and “{b}”',
    // FOTOĞRAFTAN İÇE AKTARMA (AI) — `docs/92`/`docs/97` Yolculuk A. Yükleme
    // Media sayfasında olur (`menuImportSource` slotu, yeniden icat
    // edilmez); burası yalnız OKUR ve İNCELETİR.
    'menu.item.ai.import.disclose': 'Import from a photo (AI)',
    'menu.item.ai.import.cancel': 'Cancel',
    // TOPLU okuma (`docs/96` Faz 3): bir restoranın menüsü tek fotoğrafa
    // sığmaz. Dört sayfayı tek tek okutmak aynı işi dört kez yapmaktı.
    'menu.item.ai.import.media.label': 'Choose the photos to read',
    'menu.item.ai.import.photo.failed':
        '“{name}” could not be read — the other photos still went through.',
    'menu.item.ai.import.media.empty':
        'No processed photo is available yet. Upload one on the Media page (slot: Import source) first.',
    'menu.item.ai.import.read': 'Read these photos',
    'menu.item.ai.import.reading': 'Reading…',
    'menu.item.ai.import.unavailable': 'Reading menu photos is not available right now.',
    'menu.item.ai.import.error': 'The photo could not be read. You can still add items by hand.',
    'menu.item.ai.import.fallback': 'Read by a backup provider.',
    'menu.item.ai.import.preview.heading': 'What the AI read — review before adding',
    'menu.item.ai.import.row.price.missing': 'Price could not be read — this row will be skipped',
    'menu.item.ai.import.row.uncertain': 'Not confident about this one',
    'menu.item.ai.import.apply': 'Add these to the draft',
    'menu.item.ai.import.applying': 'Adding…',
    'menu.item.ai.import.rejected.row': 'Row {row}: {reason}',
    'menu.item.image.label': 'Photo',
    'menu.item.image.none': 'No photo',
    'menu.item.image.empty':
        'No processed photo is available yet. Upload one on the Media page first.',
    // Satır içi düzenleyicinin etiketi ÜRÜN ADINI taşır.
    //
    // Ekranda aynı anda iki "Price" alanı olabilir: aşağıdaki "menüye ürün
    // ekle" formundaki ve düzenlenen satırdaki. Aynı erişilebilir ismi
    // taşıyan iki kontrol, ekran okuyucu kullanan birine "Price" der ve
    // hangisi olduğunu söylemez.
    'menu.item.price.edit.label': 'Price — {name}',
    'menu.item.allergens.edit.label': 'Allergens — {name}',
    'menu.item.price.edit.submit': 'Save price',
    'menu.item.price.edit.error.submit': 'We could not update the price. Please try again.',
    'menu.item.visibility.checkbox.label': 'Show {name}',
    'menu.item.visibility.error.submit': 'We could not update visibility. Please try again.',
    'menu.category.order.label': 'Order for {name}',
    'menu.item.order.label': 'Order for {name}',

    // Menüyü İŞLETMEK (docs/73, P0-01): silme, ad düzeltme, sıralama.
    'menu.ops.error': 'That change could not be saved. Nothing was lost — try again.',
    'menu.item.delete.confirm':
        'Remove “{name}” from this menu? Already published versions keep it.',
    'menu.category.delete.confirm':
        'Remove “{name}” and its items from this menu? Already published versions keep them.',
    'menu.rename.prompt': 'Correct the name',
    'menu.rename.error.empty': 'A name cannot be empty. Nothing was changed.',
    'menu.item.delete.label': 'Remove {name}',
    'menu.category.delete.label': 'Remove category {name}',
    'menu.rename.label': 'Rename {name}',
    'menu.move.up': 'Move {name} up',
    'menu.move.down': 'Move {name} down',
} as const;

type TranslationKey = keyof typeof en;

/**
 * Altı katalog wiring'i (CORE-08). `en` taban ve tip kaynağıdır; çeviriler
 * kısmi override'dır ve eksik anahtar `en`'e düşer. de/fr/ar/ru Stage 1'de
 * scaffold'dur (`docs/26` S1-WP03).
 */
export const t: (key: TranslationKey, vars?: Record<string, string>) => string = createTranslator(
    en,
    overridesFor('menu'),
);

/** Bu alanın İngilizce kaynak kataloğu — PO/MO/JSON zincirinin girdisi (CORE-08). */
export const menuTranslations: Record<string, string> = en;
