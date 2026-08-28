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
    // Boş durum dört soruyu birden cevaplar: ne yok, neden yok, anlamı ne,
    // şimdi ne yapabilir (docs/59).
    'workspace.qrCodes.empty.needsMenu': 'You need a menu before you can print QR codes',
    'workspace.qrCodes.empty.needsMenu.why':
        'A QR code opens your published menu, so there has to be a menu for it to open. Build yours first — it only takes a few minutes.',
    'workspace.qrCodes.empty.goToMenu': 'Go to your menu',
    'workspace.menu.previewAndPublish': 'Preview & publish',
    'workspace.menu.error.why': 'Your menu is safe — this is only a problem loading it right now.',
    'workspace.menu.error.noRetry': 'Reload the page to try again.',
    // Arıza sınıfları — her biri FARKLI bir çıkış yolu gösterir. Hepsine
    // "tekrar deneyin" demek, yalnız birinde doğru olan tavsiyeyi diğer
    // dördünde yanlış vermektir.
    'workspace.form.error.summary.title': 'Please fix the fields below',
    'workspace.brand.webAddress.label': 'Menu web address',
    'workspace.brand.webAddress.help':
        'Appears in the public link your guests open. Zabuno keeps it in step with your brand name.',
    'workspace.brand.market.label': 'Main market',
    'workspace.brand.locale.help':
        'The main language of your menu content. You can add more languages later.',
    'workspace.form.error.permission':
        'You do not have permission to change this. Ask an owner or manager of this workspace.',
    'workspace.form.error.conflict':
        'Someone else changed this while you were editing. Reload to see the current values, then apply your change again.',
    'workspace.form.error.notFound':
        'This record no longer exists. It may have been deleted in another tab.',
    'workspace.form.error.server':
        'We could not save this right now. Nothing was lost — try again in a few seconds.',
    'workspace.form.error.serverWithId':
        'We could not save this right now. Nothing was lost — try again in a few seconds. Reference: {id}',
    'workspace.form.error.network':
        'We could not reach Zabuno. Check your connection — everything you typed is still here.',
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
