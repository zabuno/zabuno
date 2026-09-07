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
    /*
        BİTTİ TANIMI GÖRÜNÜR (FF-202, `docs/101` Faz 3 "iki tık"): beş adım
        bitince yolculuk ne olduğunu ("menün yayında") ve tek somut sonraki
        işi ("masalara kodu bas") söyler; düğme karekod ekranına götürür,
        orada indirme birincil eylemdir — iki dokunuş.
    */
    'dashboard.now.allDone': 'Your menu is live. Print the codes for your tables.',
    'dashboard.now.openQr': 'Download QR codes',
    // FF-77 (`docs/102`): kartlar ve tablo başlığı katalogdan.
    'dashboard.stats.categories': 'Categories',
    'dashboard.stats.items': 'Menu items',
    'dashboard.stats.visible': 'Visible items',
    'dashboard.table.heading': 'Menu at a glance',
    'dashboard.table.caption': 'Menu item list',
    /*
        TABLO BOŞKEN NE YAZAR — `docs/121` Ö1.

        Bu satır yoktu ve tablo, `ResponsiveDataTable`'ın içinde kodda gömülü
        duran `'No data to display.'` cümlesine düşüyordu: ürünün ANA
        ekranındaki tablo, katalogdan hiç geçmeyen bir cümle gösteriyordu.
    */
    'dashboard.table.empty': 'No menu items yet.',
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
        İLK YAYINA KADAR GEÇEN SÜRE (FF-202). Yalnız bir yayın VARSA çizilir;
        sayı sunucudan gelir (`docs/110` §7 U-04). "Opening this workspace"
        der, "signing up" demez: ölçülen damga çalışma alanının açılışıdır,
        hesabın değil — ikisi farklı anlardır ve cümle ölçüleni söyler.
        Tekil/çoğul ayrı anahtar; "1 minutes" diye bir cümle yok.
    */
    'dashboard.setup.firstPublished.underMinute':
        'First published within a minute of opening this workspace.',
    'dashboard.setup.firstPublished.minute':
        'First published 1 minute after opening this workspace.',
    'dashboard.setup.firstPublished.minutes':
        'First published {count} minutes after opening this workspace.',
    'dashboard.setup.firstPublished.hour': 'First published 1 hour after opening this workspace.',
    'dashboard.setup.firstPublished.hours':
        'First published {count} hours after opening this workspace.',
    'dashboard.setup.firstPublished.days':
        'First published {count} days after opening this workspace.',
    // TAKILMA ÇIKIŞI (FF-202): kurulum kartından yardım makalesine. İki hâl:
    // bitmemişken makalenin kendisi, bitmişken sıradaki günlük iş.
    'dashboard.setup.help.stuck': 'Stuck? Read “Your first 15 minutes”',
    'dashboard.setup.help.afterSetup': 'What next: how to change a price',
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
    /*
        İLK KEZ İPUCU (FF-202, `docs/107` 1.7).

        Ölçüm (2026-09-06): Home'daki büyük düğme kullanıcıyı adımın ekranına
        bırakıyor ve o ekranda "burada ne yapılacak" diyen tek cümle yoktu;
        yardım makalesine panelden giden bağlantı sayısı SIFIRDI.

        Cümleler bu katalogda, çünkü kurulum yolculuğunun kelime dağarcığı
        TEK yerde yaşar: kutunun "devam" düğmesi Home'daki `dashboard.now.*`
        fiilini aynen kullanır — kullanıcı aynı kelimeyi iki ekranda görür ve
        aynı iş olduğunu anlar. Her cümle ne yapılacağını söyler, terim
        öğretmez (`docs/101` A2/A8) ve ölçülmemiş bir süre VAAT ETMEZ.
    */
    'dashboard.firstRun.region': 'First-time tip',
    'dashboard.firstRun.next.region': 'Next step',
    'dashboard.firstRun.dismiss': 'Hide this tip',
    'dashboard.firstRun.brand':
        'Start with the name your guests will see. Everything on this form can change later.',
    'dashboard.firstRun.location':
        'This is the place guests will scan from. A name, the city and the street are enough; more places can come later.',
    'dashboard.firstRun.menu':
        'Add your first product, or import the whole menu from a CSV file. Guests see nothing until you publish.',
    'dashboard.firstRun.publication':
        'Guests still see nothing. Tick the box and press Publish. Published the wrong list? You can go back to an earlier version.',
    'dashboard.firstRun.qr':
        'Say how many tables you have, then download the PDF. Print it once: the code keeps working when the menu changes.',
    // "Devam" kipi: bir önceki adımın ekranında, kayıt bittikten sonra.
    'dashboard.firstRun.next.location':
        'Your restaurant has a name. Next, add the place guests will scan from.',
    'dashboard.firstRun.next.menu':
        'Your location is saved. Next, put the first product on the menu.',
    // Yalnız makalede karşılığı olan adımların bağlantı etiketi vardır.
    'dashboard.firstRun.help.menu': 'How to import a menu',
    'dashboard.firstRun.help.qr': 'How to print QR codes',
    'dashboard.firstRun.help.newTab': 'opens in a new tab',
} as const;

type TranslationKey = keyof typeof en;

export const t: (key: TranslationKey, vars?: Record<string, string>) => string = createTranslator(
    en,
    overridesFor('dashboard'),
);

/** Bu alanın İngilizce kaynak kataloğu — PO/MO/JSON zincirinin girdisi (CORE-08). */
export const dashboardTranslations: Record<string, string> = en;
