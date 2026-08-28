export const shell = {
    'workspace.loading': 'Loading your workspace…',
    'workspace.error.heading': 'We could not load your workspace.',
    'workspace.error.retry': 'Retry',
    'workspace.create.heading': 'Name your workspace',
    'workspace.create.name': 'Workspace name',
    'workspace.create.submit': 'Create workspace',
    'workspace.create.error.name': 'Enter a workspace name.',
    'workspace.create.error.submit': 'We could not create your workspace. Please try again.',
    'workspace.choose.heading': 'Choose a workspace',
    'workspace.current.switch': 'Switch workspace',
    'workspace.current.logout': 'Log out',
    'workspace.current.logout.error': 'We could not log you out. Please try again.',
    'workspace.catalog.error.heading': 'We could not load your workspace catalog.',
    'workspace.catalog.error.retry': 'Retry',
    'workspace.catalog.location.label': 'Location',
    'workspace.shell.brand': 'Zabuno',
    'workspace.shell.nav.label': 'Restaurant admin',
    'workspace.shell.nav.group': 'Account',
    // Kenar çubuğu grup başlıkları. Dokuz madde tek ve adsız bir yığındı;
    // bu başlıklar onları bir SIRAYA çevirir: önce restoranı tanımla, sonra
    // menüyü kurup yayınla, sonra işi yönet.
    'workspace.shell.nav.group.primary': 'Operations',
    'workspace.shell.nav.group.management': 'Management',
    'workspace.shell.nav.group.utility': 'Settings',
    // Bölüm adları da yolculuğa göre: "Dashboard" bir gösterge paneli değil,
    // kullanıcının her gün döndüğü BAŞLANGIÇ noktasıdır; "Analytics" bir
    // modül adıdır, kullanıcının aradığı şey ise içgörüdür.
    'workspace.shell.nav.home': 'Home',
    'workspace.shell.nav.menus': 'Menus',
    'workspace.shell.nav.insights': 'Insights',
    'workspace.shell.nav.qrCodes': 'QR codes',
    'workspace.shell.nav.settings': 'Settings',
    'workspace.settings.operational.description':
        'Your brand details and your plan. These are set up once and rarely change.',
    'workspace.settings.tabs.label': 'Settings sections',
    'workspace.settings.tab.brand': 'Brand',
    'workspace.settings.tab.billing': 'Plan & billing',
    'workspace.qrCodes.operational.description':
        'Create and print the QR codes your guests scan to open the menu.',
    'workspace.qrCodes.empty.needsMenu':
        'QR codes point at a published menu. Build your menu first, then come back here to print the codes.',
    'workspace.qrCodes.empty.goToMenu': 'Go to your menu',
    'workspace.menu.previewAndPublish': 'Preview & publish',
    // Hesap menüsünün ERİŞİLEBİLİR adı. Tetikleyicide e-posta görünür ama
    // ekran okuyucuya bir e-posta adresi okumak, menünün ne olduğunu
    // söylemez.
    'workspace.account.menu.label': 'Account',
    'workspace.shell.nav.dashboard': 'Dashboard',
    'workspace.shell.nav.menuCatalog': 'Menu catalog',
    'workspace.shell.nav.brandLocations': 'Brand & Locations',
    'workspace.shell.nav.brand': 'Brand',
    'workspace.shell.nav.locations': 'Locations',
    'workspace.shell.nav.menu': 'Menu',
    'workspace.shell.nav.media': 'Media',
    'workspace.shell.nav.publication': 'Publication',
    'workspace.shell.nav.analytics': 'Analytics',
    'workspace.shell.nav.team': 'Team',
    'workspace.shell.nav.billing': 'Billing',
    'workspace.shell.currentLocation.label': 'Current location',
    'workspace.shell.globalSearch.unavailable': 'Global search unavailable',
    'workspace.shell.notifications.unavailable': 'Notifications unavailable',
    'workspace.aiCommand.trigger.label': 'Open AI command center',
    'workspace.aiCommand.title': 'AI command center',
    'workspace.aiCommand.status': 'No AI gateway is connected or available yet.',
    'workspace.aiCommand.commandLabel': 'Command',
    'workspace.aiCommand.approve': 'Approve',
    'workspace.aiCommand.affectedRecords.heading': 'Affected records',
    'workspace.aiCommand.affectedRecords.empty': 'No records are affected.',
    'workspace.aiCommand.recentCommands.heading': 'Recent commands',
    'workspace.aiCommand.recentCommands.empty': 'No commands have been run yet.',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof shell, string> {}
}
