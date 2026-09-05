import { createTranslator } from './translator';
import { overridesFor } from './generated-overrides';

const en = {
    'dashboard.heading': 'Home',
    /*
        KARŞILAMA (FF-131, AEP `DESIGN_SPEC` §2).

        Teslim paketinin Home ekranı bir SELAMLAMAYLA açılıyor; depodaki
        hâl panelin ne yaptığını anlatan bir paragrafla açılıyordu. Her
        sabah aynı ekranı açan bir restoran sahibine ürünün kendini
        tanıtması, ikinci günden itibaren gürültüdür.

        İki biçim var çünkü ad HER ZAMAN bilinmez: ilk gün marka henüz
        yazılmamıştır ve o boşluğa yer tutucu bir ad koymak, kullanıcının
        adını bildiğimizi ima etmek olurdu.
    */
    'dashboard.greeting': 'Have a good shift.',
    'dashboard.greeting.named': 'Have a good shift, {name}.',
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
    /*
        ÖLÇÜMDEN ÇIKAN ÖNERİLER (`docs/109` §6.1).

        Kaynak bu bölüme "AI önerileri" diyor. Burada öyle DENMİYOR ve sebebi
        bir üslup tercihi değil: depoda bağlı bir AI sağlayıcısı yok
        (`lib/aiAssistState.ts` sabit `disconnected`). Bir modelin yazmadığı
        cümleyi "AI önerisi" diye sunmak, olmayan bir yeteneği satmaktır.

        Cümlelerin kendisi ölçümü ADRES GÖSTERİR ("son 30 gün", "hiç
        bakılmayan"): sahibin menüsünü değiştirmesini isteyen bir satır,
        neye dayandığını söylemek zorundadır.
    */
    'dashboard.suggestions.region': 'Suggestions',
    'dashboard.suggestions.heading': '{count} suggestion from your measurements',
    'dashboard.suggestions.heading.plural': '{count} suggestions from your measurements',
    // Kaynağın değişmez cümlesi (`docs/109` §3).
    'dashboard.suggestions.rule':
        'It suggests, you approve. Nothing changes without your approval.',
    /*
        SAYI KİŞİDİR, VURUŞ DEĞİL.

        Cümle "{count} kez arandı" diyordu; oysa uç ham vuruşu değil FARKLI
        ZİYARETÇİYİ sayıyor (`COUNT(DISTINCT visitor_key)`) — arama kutusuna
        beş kez dokunan tek bir misafir orada "1"dir. "14 kez arandı" cümlesi
        sahibe on dört talep vaat eder ve o sayı yüzünden menüsüne ürün
        ekletir; oysa ölçülen şey on dört KİŞİdir, ve bu daha güçlü ama
        BAŞKA bir cümledir.

        Analitik ekranındaki liste aynı sayıyı zaten "{count} ziyaretçi" diye
        okuyordu: aynı ölçümün iki cümlesi vardı ve biri yalandı.

        Tekil/çoğul ayrı anahtar — "1 visitors" diye bir şey yok. Türkçede
        ikisi de aynı cümledir ve bu, `dashboard.suggestions.heading` ile
        aynı düzendir.
    */
    'dashboard.suggestions.search.title':
        '1 visitor searched for “{term}” but it is not on the menu',
    'dashboard.suggestions.search.title.plural':
        '{count} visitors searched for “{term}” but it is not on the menu',
    'dashboard.suggestions.search.why': 'Searches with no results · last 30 days',
    'dashboard.suggestions.search.cta': 'Open the menu',
    'dashboard.suggestions.unviewed.title': '{name} has not been opened once in the last 30 days',
    'dashboard.suggestions.unviewed.why': 'Menu engineering · never viewed',
    'dashboard.suggestions.unviewed.cta': 'Review the item',
    'dashboard.suggestions.dismiss': 'Dismiss this suggestion',
    /*
        HIZLI EYLEMLER (`docs/109` §6.2). Etiketler FİİLLE başlar: bir karo
        neyin sayfası olduğunu değil, sahibin orada ne yapacağını söyler.
    */
    'dashboard.quick.region': 'Quick actions',
    'dashboard.quick.price': 'Change a price',
    'dashboard.quick.hide': 'Hide / sold out',
    'dashboard.quick.qr': 'Download a QR code',
    'dashboard.quick.photo': 'Add a photo',
    /*
        EN ÇOK BAKILANLAR. Başlık ARALIĞI taşır: ölçülen aralığı gizleyen bir
        tablo, okuyanın "bugün" sandığı bir liste üretir ve yanlış bir bugüne
        karar verdirir.
    */
    'dashboard.topViewed.heading': 'Most viewed in the last 30 days',
    'dashboard.topViewed.all': 'See all',
    'dashboard.topViewed.column.rank': '#',
    'dashboard.topViewed.column.item': 'Item',
    'dashboard.topViewed.column.viewers': 'viewers',
    'dashboard.topViewed.column.price': 'Price',
    'dashboard.topViewed.noPrice': '—',
    /*
        Sayacın ALTINDAKİ satır. Kaynak burada bir "delta" tutuyor ("%12 ·
        geçen perşembe"); depoda geçmiş dönem karşılaştırması ÖLÇÜLMÜYOR ve
        uydurulmuyor. Yerine aynı sayının gerçek bileşimi yazılıyor: kaç ürün
        gizli. Bu, ölçülen bir olgudur ve tıpkı delta gibi sayının tek başına
        söylemediğini söyler.
    */
    'dashboard.stats.hidden': '{count} hidden',
    'dashboard.stats.allVisible': 'All visible',
} as const;

type TranslationKey = keyof typeof en;

export const t: (key: TranslationKey, vars?: Record<string, string>) => string = createTranslator(
    en,
    overridesFor('dashboard'),
);

/** Bu alanın İngilizce kaynak kataloğu — PO/MO/JSON zincirinin girdisi (CORE-08). */
export const dashboardTranslations: Record<string, string> = en;
