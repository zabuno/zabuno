import { createTranslator } from './translator';
import { overridesFor } from './generated-overrides';

const en = {
    'dashboard.heading': 'Home',
    'dashboard.loading': 'Loading your dashboard summary…',
    'dashboard.empty': 'No menu has been created for this location yet.',
    'dashboard.empty.openMenu': 'Open Menu',
    'dashboard.setup.region': 'Dashboard Setup',
    // Adım durumunun METİN karşılığı: işaret görsel, bu ekran okuyucu için
    // (docs/70).
    'dashboard.setup.step.done': 'Done',
    'dashboard.setup.step.next': 'Next step',
    'dashboard.setup.step.todo': 'Not done yet',
    'dashboard.setup.heading': 'Setup',
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
