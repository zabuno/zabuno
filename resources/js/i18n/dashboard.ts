const en = {
    'dashboard.heading': 'Dashboard',
    'dashboard.loading': 'Loading your dashboard summary…',
    'dashboard.empty': 'No menu has been created for this location yet.',
    'dashboard.empty.openMenu': 'Open Menu',
    'dashboard.setup.region': 'Dashboard Setup',
    'dashboard.setup.heading': 'Setup',
    'dashboard.setup.brand': 'Brand',
    'dashboard.setup.location': 'Location',
    'dashboard.setup.menu': 'Menu',
    'dashboard.setup.publication': 'Publication',
    'dashboard.setup.qr': 'QR',
    'dashboard.setup.menu.empty': 'No menu yet',
    'dashboard.setup.notConnected': 'Not connected yet.',
} as const;

type TranslationKey = keyof typeof en;

export function t(key: TranslationKey, vars?: Record<string, string>): string {
    const template: string = en[key] ?? key;
    if (!vars) return template;
    return Object.entries(vars).reduce<string>(
        (result, [name, value]) => result.replaceAll(`{${name}}`, value),
        template,
    );
}
