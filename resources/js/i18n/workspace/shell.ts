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
    // Bağlam paneli — menü düzenlerken sürekli sorulan ama ana alanda yeri
    // olmayan sorular (docs/60).
    'workspace.menu.inspector.title': 'This menu',
    'workspace.menu.inspector.status': 'Status',
    'workspace.menu.inspector.status.published': 'Published · version {version}',
    'workspace.menu.inspector.location': 'Location',
    'workspace.menu.inspector.categories': 'Categories',
    'workspace.menu.inspector.items': 'Items',

    // Marka panelinin cevapladığı soru: bu adı değiştirirsem nereyi
    // değiştirmiş olurum (docs/60).
    // Global oluştur (docs/64): her madde gerçekten oluşturmanın yapıldığı
    // ekrana götürür.
    'workspace.create.menu.label': 'Create',
    'workspace.create.location': 'Location',
    'workspace.create.menu': 'Menu',
    'workspace.create.qrCode': 'QR code',
    'workspace.create.teamMember': 'Team member',

    // Şubenin bölgesel alanları (docs/62): saat dilimi markanın değil
    // şubenin alanıdır.
    'workspace.location.timezone': 'Time zone',
    'workspace.location.timezone.help':
        'Opening hours and publication times use this location’s time zone.',
    'workspace.location.regional.chooseCountry': 'Choose a country',
    'workspace.location.regional.chooseTimezone': 'Choose a time zone',

    'workspace.brand.inspector.title': 'Where this brand appears',
    'workspace.brand.inspector.name': 'Brand name',
    'workspace.brand.inspector.locations': 'Locations',
    'workspace.brand.inspector.cities': 'Cities',
    'workspace.brand.inspector.manageLocations': 'Manage locations',

    // Şube panelinin cevapladığı soru: bu şubenin menüsü var mı.
    'workspace.locations.inspector.title': 'This location',
    'workspace.locations.inspector.brand': 'Brand',
    'workspace.locations.inspector.city': 'City',
    'workspace.locations.inspector.menu': 'Menu',
    'workspace.locations.inspector.menu.summary': '{categories} categories · {items} items',
    'workspace.locations.inspector.openMenu': 'Open the menu',
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
    // Analitik boş durumları (docs/66). Tek bir "veri yok" cümlesi beş ayrı
    // duruma bölündü: her birinin ÇIKIŞ YOLU farklı.
    'workspace.analytics.empty.noMenu.title': 'Analytics starts with your first menu',
    'workspace.analytics.empty.noMenu.description':
        'Scans are counted once customers can open a menu. Build one first.',
    'workspace.analytics.empty.noMenu.action': 'Build the menu',
    'workspace.analytics.empty.notPublished.title': 'Your menu is not published yet',
    'workspace.analytics.empty.notPublished.description':
        'Nothing is collected until the menu is live, because there is nothing for a customer to open.',
    'workspace.analytics.empty.notPublished.action': 'Preview and publish',
    'workspace.analytics.empty.noScans.title': 'Waiting for the first scan',
    'workspace.analytics.empty.noScans.description':
        'Your menu is live. Print the QR code or scan it with your own phone to test it.',
    'workspace.analytics.empty.noScans.action': 'View QR codes',
    'workspace.analytics.empty.range.title': 'No activity in this period',
    'workspace.analytics.empty.range.description':
        'There were scans outside this range, or none yet. Try a wider period.',
    'workspace.analytics.empty.range.action': 'Show the last 30 days',
    // Ekran kullanıcıyı GÖTÜREMEDİĞİNDE nereye gideceğini söyler. Açıklamayı
    // tekrarlamak, aynı cümleyi iki kez okutmak olurdu.
    'workspace.analytics.empty.useSidebar': 'Open that screen from the sidebar.',

    // Omnibox (docs/65). Eskiden burada dokuz `aiCommand` anahtarı vardı ve
    // hepsi bağlı olmayan bir AI merkezinin devre dışı alanlarını
    // adlandırıyordu; yüzeyle birlikte kaldırıldılar.
    'workspace.omnibox.trigger.label': 'Search, go to, or create',
    'workspace.omnibox.title': 'Search and go',
    'workspace.omnibox.input.label': 'Search',
    'workspace.omnibox.input.placeholder': 'Type to search menus, locations and screens',
    'workspace.omnibox.group.goTo': 'Go to',
    'workspace.omnibox.group.create': 'Create',
    'workspace.omnibox.group.records': 'In this workspace',
    'workspace.omnibox.empty': 'Nothing matches that search in this workspace.',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof shell, string> {}
}
