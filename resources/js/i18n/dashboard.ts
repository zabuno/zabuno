import { createTranslator } from './translator';
import { overridesFor } from './generated-overrides';

const en = {
    'dashboard.heading': 'Home',
    'dashboard.loading': 'Loading your dashboard summary…',
    'dashboard.empty': 'No menu has been created for this location yet.',
    'dashboard.empty.openMenu': 'Open Menu',
    'dashboard.setup.region': 'Dashboard Setup',
    // `docs/101` A1/A6 (FF-73): Home'da TEK "şimdi" — bitmemiş ilk adım, fiiliyle.
    'dashboard.now.region': 'What to do now',
    'dashboard.now.heading': 'Now',
    'dashboard.now.brand': 'Name your restaurant',
    'dashboard.now.location': 'Add your location',
    'dashboard.now.menu': 'Add your first product',
    'dashboard.now.publication': 'Publish your menu',
    'dashboard.now.qr': 'Print your QR codes',
    'dashboard.now.allDone': 'Everything is set up. Your guests can scan the menu.',
    'dashboard.now.openQr': 'Open QR codes',
    // FF-77 (`docs/102`): kartlar ve tablo başlığı katalogdan.
    'dashboard.stats.categories': 'Categories',
    'dashboard.stats.items': 'Menu items',
    'dashboard.stats.visible': 'Visible items',
    'dashboard.table.heading': 'Menu at a glance',
    'dashboard.table.caption': 'Menu item list',
    'dashboard.table.column.item': 'Item',
    'dashboard.table.column.visible': 'Visible',
    // Adım durumunun METİN karşılığı: işaret görsel, bu ekran okuyucu için
    // (docs/70).
    'dashboard.setup.step.done': 'Done',
    'dashboard.setup.step.next': 'Next step',
    'dashboard.setup.step.todo': 'Not done yet',
    'dashboard.setup.heading': 'Setup',
    /*
        KURULUM ŞERİDİ (FF-100). Beş adım eşit ağırlıkta, mavi bağlantılar
        hâlinde duruyordu: hangisinin bittiği yalnız ekran okuyucuya
        söyleniyor, gözle bakan kişi beş aynı satır görüyordu. Ve kurulum
        bittikten sonra kart her gün aynı yeri kaplamaya devam ediyordu.
    */
    'dashboard.setup.progress': '{done}/{total} done',
    'dashboard.setup.progress.next': 'next: {step}',
    'dashboard.setup.complete': 'Setup complete',
    'dashboard.setup.complete.summary': 'Your restaurant is ready for guests.',
    'dashboard.setup.toggle': 'Show the steps',
    'dashboard.setup.brand': 'Brand',
    'dashboard.setup.location': 'Location',
    'dashboard.setup.menu': 'Menu',
    'dashboard.setup.publication': 'Publication',
    'dashboard.setup.qr': 'QR',
    'dashboard.setup.menu.empty': 'No menu yet',
    'dashboard.setup.notConnected': 'Not connected yet.',
    'dashboard.setup.statusUnavailable': 'Status unavailable.',
    'dashboard.setup.checking': 'Checking…',
    'dashboard.setup.published': 'Published #{id}',
    'dashboard.setup.qr.activeCount': '{count} active QR',
    'dashboard.setup.qr.activeCount.plural': '{count} active QRs',
} as const;

type TranslationKey = keyof typeof en;

export const t: (key: TranslationKey, vars?: Record<string, string>) => string = createTranslator(
    en,
    overridesFor('dashboard'),
);

/** Bu alanın İngilizce kaynak kataloğu — PO/MO/JSON zincirinin girdisi (CORE-08). */
export const dashboardTranslations: Record<string, string> = en;
