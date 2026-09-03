/**
 * TANITIM SİTESİ kataloğu — kaydolmamış bir ziyaretçinin okuduğu metin.
 *
 * KAYNAK DİL İNGİLİZCE, diğer bütün alanlar gibi. Misafir menüsünden
 * (`guest.ts`) ayrı: orası RESTORANIN yüzeyi, burası ÜRÜNÜN yüzeyi.
 *
 * `docs/88` (P1-01) ile açıldı. Ana sayfanın eski metinleri henüz burada
 * değil; onlar `lang/untranslatable-debt.json` içinde kayıtlı borç olarak
 * duruyor ve ayrı bir turda taşınacak. Bu katalog YENİ yüzeylerle başladı,
 * çünkü borcu büyütmemek onu bir seferde kapatmaktan önce gelir.
 */
export const siteTranslations = {
    // --- Masterpage: gezinti ve altbilgi (`docs/100` §2) ------------------
    'site.skipToContent': 'Skip to main content',
    'site.nav.features': 'Features',
    'site.nav.howItWorks': 'How it works',
    'site.nav.pricing': 'Pricing',
    'site.nav.help': 'Help',
    'site.nav.contact': 'Contact',
    'site.nav.login': 'Log in',
    'site.nav.register': 'Create account',
    'site.footer.product': 'Product',
    'site.footer.legal': 'Legal',
    'site.footer.terms': 'Terms',
    'site.footer.privacy': 'Privacy',
    'site.footer.kvkk': 'KVKK',
    'site.footer.tagline': 'Your menu behind a QR code, kept up to date by your own team.',
    // --- Fiyat -----------------------------------------------------------
    'site.engineering.title': 'Zabuno — Engineering',
    'site.pricing.heading': 'Pricing',
    'site.pricing.lead': 'What a restaurant pays to publish its menu behind a QR code.',
    /*
        Boş bir fiyat tablosu, ziyaretçiye "bu ürün hazır değil" dedirtir.
        Sayfa DURUMU söyler ve bir ÇIKIŞ YOLU bırakır: boş bir hâl bir hata
        değildir, ama bir çıkmaz da olmamalıdır (`docs/66`).
    */
    'site.pricing.empty':
        'Plan prices are not published yet. Tell us about your restaurant and we will answer with a price for your case.',
    'site.pricing.empty.cta': 'Contact us',
    // Tutarı girilmemiş bir planı "0" ya da "ücretsiz" göstermek,
    // tutulmayacak bir söz vermek olurdu.
    'site.pricing.perRestaurant': 'Priced per restaurant.',
    'site.pricing.perRestaurant.cta': 'Contact us',
    'site.pricing.unsure': 'Not sure which one fits?',
    'site.pricing.unsure.cta': 'Ask us',

    /*
        HER PLANDA OLAN, bir kez söylenir.

        Yetenek listesi EK yetkileri anlatır; temel zinciri değil. Yalnız
        onları göstermek, ücretsiz kademeyi "hiçbir şey içermiyor" gibi
        gösterirdi — oysa menü, yayın, karekod ve misafir sayfası her planda
        var ve bunu bir test donduruyor (`docs/90`).
    */
    'site.pricing.included.heading': 'Every plan includes',
    'site.pricing.included.body':
        'Your menu, publishing with one-click rollback, QR codes and the guest page, CSV import and export, photos, and marking a dish sold out for the day.',
    'site.pricing.free': 'Free',
    'site.pricing.perMonth': 'per month',
    'site.pricing.adds': 'Adds',

    // Yetenek anahtarları GELİŞTİRİCİ dilidir (`qr.bulk-generation`);
    // müşteri sayfasında insanca karşılıkları görünür.
    'site.plan.qrBulk': 'Bulk QR codes for a whole room of tables',
    'site.plan.analytics':
        'Analytics: what guests look at, and what they search for and cannot find',
    'site.plan.team': 'Team members with roles you control',

    // --- İletişim --------------------------------------------------------
    'site.contact.heading': 'Contact',
    'site.contact.lead':
        'Ask about pricing, a pilot, or anything that is in your way. We keep every message; nothing is lost.',
    // Teyit EKRANDA: "gönderildi" demeyen bir form, gönderilip
    // gönderilmediğini bilmeyen bir kullanıcı bırakır.
    'site.contact.sent':
        'Thank you — we received your message and will reply to the address you gave.',
    // Etiket ŞART: yer tutucu bir etiket değildir ve ekran okuyucu onu alan
    // adı olarak okumaz.
    'site.contact.name': 'Your name',
    'site.contact.email': 'Your email',
    'site.contact.message': 'Your message',
    'site.contact.submit': 'Send message',
    // Bal küpü etiketi: insan bunu görmez, ama ekran okuyucu görürse ne
    // yapacağını bilmeli.
    'site.contact.honeypot': 'Leave this empty',

    // --- Ana sayfadaki iki yeni cümle ------------------------------------
    'site.home.faq.cost.question': 'What does it cost?',
    'site.home.faq.cost.answer':
        'Prices come from our plan catalogue, so what you read there is what we charge.',
    'site.home.contact.lead':
        'Ask about pricing, a pilot, or anything that is in your way. We keep every message; you get a confirmation on screen.',
    'site.home.contact.cta': 'Write to us',
} as const;

export type SiteTranslationKey = keyof typeof siteTranslations;

export default siteTranslations;
