export const ordering = {
    // Kenar çubuğu etiketi. "Sipariş" değil "Siparişler": garson bir listeye
    // bakar, tek bir siparişe değil.
    'workspace.shell.nav.orders': 'Orders',

    'workspace.orders.heading': 'Orders',
    'workspace.orders.tab.queue': 'Queue',
    'workspace.orders.tab.kitchen': 'Kitchen monitor',
    'workspace.orders.tab.settings': 'Settings',

    /*
        TAZELEME DÜRÜSTLÜĞÜ (`docs/115` §6).

        Ekran "canlı" ya da "anlık" DEMEZ, çünkü değil: yoklama on saniyede
        bir. Mutfakta donmuş bir ekranla dolu bir ekran aynı görünür; tek
        ayırt edici şey son güncelleme anıdır.
    */
    'workspace.orders.updated': 'Updated {time}',
    'workspace.orders.updated.never': 'Not updated yet',
    'workspace.orders.stale': 'The last refresh failed. What you see may be out of date.',
    'workspace.orders.refresh': 'Refresh now',

    // --- S4, garson kuyruğu ---------------------------------------------
    'workspace.orders.queue.region': 'Orders waiting for approval',
    'workspace.orders.queue.loading': 'Loading the queue…',
    'workspace.orders.queue.error.title': 'The queue could not be loaded',
    'workspace.orders.queue.error.description':
        'Nothing was lost: orders stay in the queue. Try again.',
    'workspace.orders.queue.empty.title': 'No orders waiting',
    'workspace.orders.queue.empty.description':
        'New orders from the tables land here. The list refreshes on its own.',
    'workspace.orders.queue.empty.closed.title': 'Ordering is switched off',
    // Y1: kapalıyken misafir sepeti görür ama gönderemez ve SEBEBİNİ okur.
    // Panelde de aynı dürüstlük: boş liste sessiz bir akşam gibi görünmemeli.
    'workspace.orders.queue.empty.closed.description':
        'Guests can read the menu but cannot send an order. Turn ordering on under Settings.',
    'workspace.orders.queue.count': '{count} waiting',
    'workspace.orders.queue.waiting': 'Waiting {minutes} min',
    'workspace.orders.queue.waiting.justNow': 'Just arrived',
    'workspace.orders.table': 'Table {name}',
    'workspace.orders.total': 'Total',
    'workspace.orders.quantity': '{count} ×',
    'workspace.orders.allergens': 'Allergens: {list}',
    'workspace.orders.price.unavailable': 'Price unavailable',

    'workspace.orders.confirm': 'Approve',
    'workspace.orders.confirm.pending': 'Approving…',
    'workspace.orders.reject': 'Reject',
    'workspace.orders.reject.heading': 'Why are you rejecting this order?',
    // G3: sebep misafirin ekranında görünür. Bu cümle garsona ne yazdığını
    // hatırlatır — okunacağını bilmek yazılanı değiştirir.
    'workspace.orders.reject.help': 'The guest reads this on their own screen.',
    'workspace.orders.reject.label': 'Reason',
    'workspace.orders.reject.required': 'Write a reason. An order is never rejected in silence.',
    'workspace.orders.reject.submit': 'Reject order',
    'workspace.orders.reject.cancel': 'Back',
    // G5: ikinci onay denemesi sessizce geçmez, durumu söyler.
    'workspace.orders.conflict': 'Someone was faster: this order is now “{status}”.',
    'workspace.orders.actionFailed': 'That did not go through. Try again.',

    // --- S5, mutfak monitörü --------------------------------------------
    'workspace.orders.kitchen.region': 'Kitchen monitor',
    'workspace.orders.kitchen.loading': 'Loading the kitchen monitor…',
    'workspace.orders.kitchen.error.title': 'The kitchen monitor could not be loaded',
    'workspace.orders.kitchen.error.description': 'Approved orders are safe. Try again.',
    'workspace.orders.kitchen.empty.title': 'Nothing to cook',
    // K1: bekleyen sipariş mutfağa HİÇ görünmez. Boş ekran bunu söylemeli,
    // yoksa aşçı "sipariş gelmiyor mu" diye garsona sorar.
    'workspace.orders.kitchen.empty.description':
        'Orders appear here once service approves them. Waiting orders are not shown.',
    'workspace.orders.kitchen.fullscreen': 'Full screen',
    'workspace.orders.kitchen.fullscreen.exit': 'Leave full screen',
    // Tarayıcı desteklemiyorsa dürüstçe düşülür; olmayan bir yetenek
    // varmış gibi bir düğme çizilmez (`docs/115` S5).
    'workspace.orders.kitchen.fullscreen.unavailable':
        'This browser does not offer full screen. Use the browser’s own full-screen key.',
    'workspace.orders.kitchen.start': 'Start cooking',
    'workspace.orders.kitchen.ready': 'Mark ready',
    'workspace.orders.kitchen.deliver': 'Handed to the table',
    'workspace.orders.kitchen.status.confirmed': 'Approved',
    'workspace.orders.kitchen.status.preparing': 'Cooking',
    'workspace.orders.kitchen.status.ready': 'Ready',
    // Mutfak monitörü masaüstü/tablet yüzeyidir (`docs/54`): kodu telefon
    // paketine hiç inmez. Telefonda dürüst bir cümle durur, boş bir ekran
    // değil.
    'workspace.orders.kitchen.desktopOnly.title': 'The kitchen monitor needs a bigger screen',
    'workspace.orders.kitchen.desktopOnly.description':
        'It is built for the screen on the kitchen wall. Open this page on a tablet or a computer.',

    // --- S6, sipariş ayarları ve geçmiş ---------------------------------
    'workspace.orders.settings.region': 'Ordering settings',
    'workspace.orders.settings.switch.label': 'Accept orders from tables',
    'workspace.orders.settings.switch.on': 'Ordering is on',
    'workspace.orders.settings.switch.off': 'Ordering is off',
    // Göç varsayılanı kapalı yazdı ve bunun sebebi ekranda da yazılır:
    // sipariş alma, panelde birinin BAKMASINI gerektiren tek yetenektir.
    'workspace.orders.settings.switch.help':
        'While it is off, guests read the menu but cannot send an order. Somebody has to watch the queue while it is on.',
    'workspace.orders.settings.switch.saving': 'Saving…',
    'workspace.orders.settings.switch.error': 'The switch could not be changed. Try again.',
    'workspace.orders.settings.switch.readOnly':
        'Only the workspace owner can switch ordering on or off.',
    'workspace.orders.settings.loading': 'Loading ordering settings…',
    'workspace.orders.settings.error.title': 'Ordering settings could not be loaded',
    'workspace.orders.settings.error.description': 'Nothing was changed. Try again.',

    'workspace.orders.history.region': 'Order history',
    'workspace.orders.history.heading': 'Order history',
    // Y2: geçmiş silinmez. Cümle bir övünme değil, bir sözleşmedir.
    'workspace.orders.history.description': 'Every order stays here. Nothing is deleted.',
    'workspace.orders.history.loading': 'Loading the history…',
    'workspace.orders.history.error.title': 'The history could not be loaded',
    'workspace.orders.history.error.description': 'Nothing was lost. Try again.',
    'workspace.orders.history.empty.title': 'No orders yet',
    'workspace.orders.history.empty.description':
        'Once tables start ordering, every order stays on this list.',
    'workspace.orders.history.rejected': 'Rejected: {reason}',
    'workspace.orders.history.previous': 'Previous',
    'workspace.orders.history.next': 'Next',
    'workspace.orders.history.page': 'Page {page} of {pageCount}',

    'workspace.orders.status.pending': 'Waiting',
    'workspace.orders.status.confirmed': 'Approved',
    'workspace.orders.status.preparing': 'Cooking',
    'workspace.orders.status.ready': 'Ready',
    'workspace.orders.status.delivered': 'Delivered',
    'workspace.orders.status.cancelled': 'Cancelled by the guest',
    'workspace.orders.status.rejected': 'Rejected',

    /*
        --- Y3, PLAN KAPISI (`docs/115` Y3) ---------------------------------

        Ölçülen kusur: şalter panelden açılabiliyordu ama plan sipariş almayı
        içermiyorsa misafirin siparişi reddediliyordu. Sahip hizmeti açtığını
        sanıyor, mutfağa hiçbir şey düşmüyordu.

        HAK, ADIYLA ANILIR — ama iç anahtarıyla değil. Sunucu ekrana hangi
        hakkın eksik olduğunu bildiriyor; ekran onu restoran sahibinin
        dilinde adlandırıyor. `ordering.basic` dizesini ekrana basmak bu
        deponun kendi kelime dağarcığı kuralının ihlali olurdu (`docs/53`:
        iç anahtar adı restoran yüzeyinde yazmaz) ve zaten sahibin plan
        sayfasında gördüğü şey de o dize değil.

        Ad TEK YERDE. Dört ekran aynı hakkı anıyor; dördüne ayrı yazılsaydı
        biri değiştiğinde sahip aynı kısıtı iki farklı adla okurdu.
    */
    'workspace.orders.plan.name': 'Taking orders from tables',
    'workspace.orders.plan.action': 'View your plan',
    // Kapalı bir şalter ile planda olmayan bir hizmet aynı görünür ama
    // çıkış yolları farklıdır: biri bir tık, öbürü bir plan değişikliği.
    'workspace.orders.settings.plan.missing':
        '“{name}” is not part of your current plan. While it is missing you cannot switch ordering on, and a guest who scans a table code still reads the menu but cannot send an order.',
    /*
        HAK SONRADAN DÜŞTÜĞÜNDE söylenen cümle. Şalteri sessizce kapatmak
        daha temiz görünürdü ve daha kötü olurdu: sahip ayarının arkasından
        değiştiğini bilmez, planı geri geldiğinde neyi kaybettiğini de
        bilmezdi. Ekran gerçeği söyler, ayarı sahibin elinde bırakır.
    */
    'workspace.orders.settings.plan.stuckOn':
        'Ordering is switched on, but “{name}” is no longer part of your plan, so no order can reach the kitchen. Nobody switched it off for you: that stays your decision, and orders start arriving again as soon as the plan includes it.',
    'workspace.orders.queue.empty.plan.title': 'Orders cannot reach you',
    // Boş liste "bugün sipariş yok" demektir ve bu YANLIŞ bir cümledir:
    // sipariş gelmiyor değil, gelemiyor.
    'workspace.orders.queue.empty.plan.description':
        'This is not a quiet evening: “{name}” is not part of your current plan, so every order a guest sends is turned down. Nothing is lost — the queue starts filling the moment the plan includes it.',
    'workspace.orders.kitchen.plan.title': 'Nothing can reach the kitchen',
    'workspace.orders.kitchen.plan.description':
        '“{name}” is not part of your current plan. An empty monitor would look like a quiet evening; it is not — guests cannot send an order at all.',

    'workspace.orders.permission.title': 'Orders are not part of your role',
    'workspace.orders.permission.description':
        'Ask the workspace owner if you need to see the order queue.',

    'workspace.orders.prerequisite.title': 'Pick a location first',
    'workspace.orders.prerequisite.description':
        'Orders belong to a branch: a table, a queue and a kitchen are all part of one location.',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof ordering, string> {}
}
