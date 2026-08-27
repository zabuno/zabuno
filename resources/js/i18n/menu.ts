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
    'menu.product.name.label': 'Product name',
    'menu.product.create.submit': 'Add product',
    'menu.product.name.error.required': 'Enter a product name.',
    'menu.product.create.error.submit': 'We could not add the product. Please try again.',
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
    'menu.item.price.edit.submit': 'Save price',
    'menu.item.price.edit.error.submit': 'We could not update the price. Please try again.',
    'menu.item.visibility.checkbox.label': 'Show {name}',
    'menu.item.visibility.error.submit': 'We could not update visibility. Please try again.',
    'menu.category.order.label': 'Order for {name}',
    'menu.item.order.label': 'Order for {name}',
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
