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
    /*
        MARKA ADI da katalogdadır (FF-98). Bir marka adı çoğu dilde aynı
        kalır; ama şablona gömüldüğü sürece sahibi onu hiçbir yerden
        değiştiremez ve çevrilemez borç sıfıra inmez. Aynı karar çalışma
        alanı kabuğunda zaten verilmişti (`workspace.shell.brand`).
    */
    'site.brand': 'Zabuno',
    /* Belge başlığının son eki: "Fiyat — Zabuno". */
    'site.title.suffix': 'Zabuno',
    'site.nav.primary': 'Primary',
    /*
        YASAL BELGE ŞABLONUNUN ETİKETLERİ (FF-198, `docs/124`).

        Belge METNİ burada değil: `app/Infrastructure/Legal/Documents` içinde,
        İngilizce kaynak olarak yaşar ve yardım makaleleri gibi bir bütün
        halinde okunur. Burada yalnız şablonun kendi sözcükleri var: sürüm,
        yürürlük, içindekiler, inceleme notu ve çerez tercihi.
    */
    'site.legal.review.pending':
        'This text is pending legal review. It describes what the product does today and may be reworded after that review.',
    'site.legal.version': 'Version',
    'site.legal.effective': 'Effective from',
    'site.legal.contents': 'Contents',
    'site.legal.cookies.preference.heading': 'Your measurement choice',
    'site.legal.cookies.preference.current': 'Current choice:',
    'site.legal.cookies.preference.granted': 'measurement allowed',
    'site.legal.cookies.preference.denied': 'measurement declined',
    'site.legal.cookies.preference.undecided': 'not decided yet, so measurement stays off',
    /*
        ÇEREZ SEÇİM ŞERİDİ (FF-198). Kısa: dar ekranda iki satır ve üç düğme.
        Hiçbir üçüncü taraf aracı karar verilmeden yüklenmez ve şerit bunu
        söyler; "kabul etmezsen site çalışmaz" demez, çünkü çalışır.
    */
    'site.consent.label': 'Cookie choice',
    'site.consent.body':
        'We load measurement tools only if you allow them. Nothing is loaded until you decide.',
    'site.consent.accept': 'Allow measurement',
    'site.consent.decline': 'Decline',
    'site.consent.link': 'Cookie Policy',
    /*
        HESAP VERİSİ TALEBİ (FF-169, `docs/110` P0-09).

        Bu beş dize bir HUKUKİ METİN DEĞİLDİR ve öyle okunmamalıdır: hiçbiri
        bir hak saymaz, bir kanun maddesine atıf yapmaz ve bir süre taahhüt
        etmez. Söyledikleri yalnız ürünün BUGÜN yapabildiği şeydir — menü
        zaten indirilebilir (`MenuCsvRoundTripTest`), gerisi için çalışan bir
        iletişim yolu vardır. Sayfanın hukuki hükmü hâlâ nitelikli incelemeyi
        bekliyor ve üstteki `site.legal.pending` bunu söylemeye devam ediyor.
    */
    'site.legal.dataRequest.heading': 'The data in your account',
    'site.legal.dataRequest.body':
        'You can download your menu yourself, as a CSV file, from your workspace at any time — you do not need to ask us for it. For anything else held in your account, write to us using the contact form and say what you are asking for.',
    'site.legal.dataRequest.cta': 'Open the contact form',
    'site.legal.dataRequest.addressLabel': 'Where the request is sent',
    /*
        GİRİLMEMİŞ BİLGİ SÖYLENİR. İkinci cümle şart: adres yokken sahip
        "demek ki hiçbir yere yazamam" diye okumamalı — form çalışıyor ve
        mesaj saklanıyor (`StoreContactMessageController`).
    */
    'site.legal.dataRequest.addressMissing':
        'This information has not been entered yet. A request sent through the contact form still reaches us and is recorded.',

    /*
        EKSİK SÖZLEŞME BANDI (FF-216).

        Bu üç dize bir hukuki metin değil, bir DURUM bildirimidir: sözleşmenin
        tarafı olan tüzel kişi henüz yayınlanmadığı için belge tamam değildir
        ve okuyucu buna dayanmamalıdır. Renk tek başına bunu anlatamaz —
        başlık kelimeyle söyler.

        "Do not rely on it" cümlesi bilerek sert: bu bandın gösterildiği
        sayfa aynı anda arama motoruna kapatılır ve üretimde ödeme bu hâlde
        başlatılamaz (`ManageCheckout`). Bandı yumuşatmak, üçünden yalnız
        birini yumuşatmak olurdu.
    */
    'site.legal.incomplete.heading': 'This document is not complete yet.',
    'site.legal.incomplete.body':
        'The legal identity of the seller has not been published yet, so this text does not name the party you would be contracting with. Read it as a draft: do not rely on it as a contract, and ask us before you act on it.',
    'site.legal.incomplete.fields': 'Not entered yet:',

    /*
        ŞİRKET KİMLİĞİNİN ETİKETLERİ (FF-216) — `/about`, `/contact` ve eksik
        alan bandı bu YEDİ etiketi paylaşır (`CompanyIdentity`). Alan adı →
        etiket eşlemesi kodda tek yerde; dizeler burada tek yerde.
    */
    'site.company.legalName': 'Registered name',
    'site.company.address': 'Registered address',
    'site.company.mersis': 'MERSIS number',
    'site.company.taxOffice': 'Tax office',
    'site.company.taxNumber': 'Tax number',
    'site.company.email': 'E-mail',
    'site.company.phone': 'Phone',
    /* GİRİLMEMİŞ DEĞER GİZLENMEZ. Satırı atlamak, o alanın hiç istenmediği
       izlenimi verirdi; uydurmak ise satıcıyı yanlış göstermek olurdu. */
    'site.company.value.missing': 'Not entered yet',

    'site.skipToContent': 'Skip to main content',
    'site.nav.features': 'Features',
    'site.nav.howItWorks': 'How it works',
    'site.nav.pricing': 'Pricing',
    'site.nav.help': 'Help',
    'site.nav.about': 'About us',
    'site.nav.contact': 'Contact',
    'site.nav.login': 'Log in',
    'site.nav.register': 'Create account',
    /*
        KABUK MENÜSÜ (FF-190).

        Dar ekran tabandır (`docs/118` E1): 320 pikselde marka, beş gezinti
        bağlantısı ve iki hesap düğmesi yan yana sığmaz. Çubukta yalnız iki
        şey durur ve bu, açılır bölmeyi açan sözcüktür.
    */
    'site.nav.menu': 'Menu',
    /*
        MEGA MENÜ GRUBU — sahibin kendi site haritasındaki üst menü
        (`docs/106` §3.1). Bu maddeler sayfa kütüğündeki canonical yollara
        bağlanır; yayınlanmamış olanı gezintide HİÇ görünmez, dolayısıyla
        bugün bu grubun tamamı gizlidir.
    */
    'site.nav.explore': 'Explore',
    'site.nav.product': 'Product',
    'site.nav.solutions': 'Solutions',
    'site.nav.integrations': 'Integrations',
    'site.nav.customers': 'Customers',
    'site.nav.resources': 'Resources',
    /* Hesap eylemlerinin grup adı — ekran okuyucu iki bağlantının niye bir
       arada durduğunu buradan öğrenir. */
    'site.nav.account': 'Account',
    'site.footer.product': 'Product',
    /*
        ŞİRKET GRUBU (FF-237). "Hakkımızda" ve "İletişim" FF-232'de `Ürün`
        başlığının altındaydı; ikisi de ürün değil, SATICI hakkındadır ve
        ödeme kuruluşunun üye iş yeri incelemesi onları o adla arar.
    */
    'site.footer.company': 'Company',
    'site.footer.legal': 'Legal',
    'site.footer.terms': 'Terms',
    'site.footer.privacy': 'Privacy',
    'site.footer.kvkk': 'KVKK',
    // Uzaktan satışın belgeleri (FF-198).
    'site.footer.distanceSales': 'Distance Sales Agreement',
    'site.footer.preInformation': 'Preliminary Information Form',
    /* Teslimat/ifa AYRI bir başlık (FF-216): dijital bir hizmette
       "teslimat" hesabın ne zaman aktifleştiğidir ve o başlık adıyla
       aranır. */
    'site.footer.delivery': 'Delivery and Performance Terms',
    'site.footer.refundPolicy': 'Cancellation and Refund Policy',
    'site.footer.cookies': 'Cookie Policy',
    /*
        ON ÜÇ BELGENİN TAMAMI ALTBİLGİDE (FF-237).

        Aşağıdaki beş etiket FF-232'de yoktu ve karşılıkları olan beş sayfa
        canlıda 200 dönüyordu — yani yazılmış, incelenmiş ve yayınlanmış bir
        sözleşme, onu arayan kişinin bakacağı tek yerde bulunamıyordu.

        Etiketler belgelerin KENDİ başlıklarıyla aynı sözcüklerdir
        (`app/Infrastructure/Legal/Documents`): altbilgide bir ad, sayfada
        başka bir ad görmek, aynı belgenin iki olduğunu düşündürür.
    */
    'site.footer.marketingConsent': 'Electronic Commercial Message Consent',
    'site.footer.dataProcessing': 'Data Processing Agreement',
    'site.footer.sla': 'Service Level Terms',
    'site.footer.acceptableUse': 'Acceptable Use Policy',
    'site.footer.thirdPartyLicenses': 'Third-Party Licences',
    /*
        ALT SATIRIN GERİ DÖNÜŞ YOLU (FF-237). Altbilgi 320 pikselde bir
        ekran boyundan uzun; sonuna varan kişinin gezintiye dönmek için
        parmağıyla geri kaydırması gerekirdi. Hedef `#main-content`:
        atlama bağlantısının zaten kullandığı çıpa, yani ikinci bir kimlik
        icat edilmedi.
    */
    'site.footer.backToTop': 'Back to top',
    'site.footer.tagline': 'Your menu behind a QR code, kept up to date by your own team.',
    /*
        pSEO içerik menüleri bandını AÇAN sözcük (FF-232). Bandın içindeki
        başlıklar ve bağlantılar sayfa kütüğünden gelir; katalogda yalnız bu
        tek dize durur.
    */
    'site.footer.contentMenus': 'Browse all pages',
    // --- Fiyat -----------------------------------------------------------
    'site.engineering.title': 'Zabuno — Engineering',
    /*
        Kabuk sekme başlıkları (FF-93). Blade'e sabit yazılıydılar: Türkçe
        bir kullanıcı arayüzü Türkçe görürken sekmede "Log in" okuyordu ve
        sahibi o dizeyi hiçbir PO dosyasında bulamıyordu.
    */
    'site.title.login': 'Zabuno — Log in',
    'site.title.register': 'Zabuno — Register',
    'site.title.forgotPassword': 'Zabuno — Forgot password',
    'site.title.resetPassword': 'Zabuno — Reset password',
    'site.title.verifyEmail': 'Zabuno — Verify your email',
    'site.title.emailVerified': 'Zabuno — Email verified',
    'site.title.invitation': 'Zabuno — Team invitation',
    'site.title.workspace': 'Zabuno — Workspace',
    'site.title.platform': 'Zabuno — Platform Admin',
    /*
        ZABUNO SERVICE PASS — hazırlanıyor sayfası (FF-117, yönerge §8).

        Ziyaretçiye teknik durum adı (`content_draft`) YAZILMAZ: ona hiçbir şey
        anlatmaz ve ürünü içeriden konuşur gösterir. Her durumun okunabilir bir
        cümlesi var. Sahte ilerheme yüzdesi ve uydurma geri sayım yok —
        tutulmayacak bir söz, hiç söz vermemekten kötüdür.
    */
    'site.pageState.title': 'Hazırlanıyor',
    'site.pageState.headline': 'Bu sayfa henüz servise çıkmadı.',
    'site.pageState.lede':
        'İçerik, tasarım, arama görünürlüğü ve kalite kontrolü katman katman hazırlanıyor.',
    'site.pageState.maintenanceHeadline': 'Bu sayfa kısa süreliğine bakımda.',
    'site.pageState.maintenanceLede':
        'Sayfa yayındaydı ve geri gelecek. Bu sırada diğer sayfalar çalışmaya devam ediyor.',
    'site.pageState.pageLabel': 'Sayfa',
    'site.pageState.stageLabel': 'Durum',
    'site.pageState.updatedLabel': 'Son güncelleme',
    'site.pageState.home': 'Ana sayfaya dön',
    'site.pageState.explore': 'Çalışan sayfaları keşfet',
    'site.pageState.contact': 'İletişime geç',
    'site.pageState.planned': 'Sıraya alındı',
    'site.pageState.scaffolded': 'İskeleti hazırlandı',
    'site.pageState.content_draft': 'Türkçe içeriği hazırlanıyor',
    'site.pageState.content_review': 'İçeriği kontrol ediliyor',
    'site.pageState.design_review': 'Görsel düzeni hazırlanıyor',
    'site.pageState.seo_review': 'Arama görünürlüğü kontrol ediliyor',
    'site.pageState.qa': 'Son kalite kontrolünde',
    'site.pageState.approved': 'Servise çıkmayı bekliyor',
    'site.pageState.published': 'Yayında',
    'site.pageState.maintenance': 'Kısa süreli bakımda',
    'site.pageState.retired': 'Yayından kaldırıldı',
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
    // "Fotoğraflar" TEK BAŞINA yazılamaz (`docs/122` Y6): yüklemek, saklamak
    // ve panelde görmek her planda; MİSAFİRE göstermek `menu.rich-media`
    // hakkına bağlı. Kısaltmak, sayfanın kendi eleştirdiği şeyi yapmak —
    // önemli yarısını gizlemek — olurdu.
    'site.pricing.included.body':
        'Your menu, publishing with one-click rollback, QR codes and the guest page, CSV import and export, uploading and keeping photos of your dishes, and marking a dish sold out for the day.',
    'site.pricing.free': 'Free',
    'site.pricing.perMonth': 'per month',
    'site.pricing.adds': 'Adds',

    // Yetenek anahtarları GELİŞTİRİCİ dilidir (`qr.bulk-generation`);
    // müşteri sayfasında insanca karşılıkları görünür.
    'site.plan.qrBulk': 'Bulk QR codes for a whole room of tables',
    'site.plan.analytics':
        'Analytics: what guests look at, and what they search for and cannot find',
    'site.plan.team': 'Team members with roles you control',
    'site.plan.branding': 'Your own colours and type on the guest menu',
    // Söz, hattın gerçekten yaptığı kadar: misafir gönderir, personel
    // onaylar, mutfak görür. "Anında" denmez — kanal yoklamadır
    // (`docs/115` §6) ve tutulmayacak bir hız sözü vermek istemiyoruz.
    'site.plan.ordering':
        'Guests order from the table; your staff confirms and the kitchen sees it',
    // Söz, yüzeyin gerçekten yaptığı kadar: hak GÖSTERMEYİ açar, yüklemeyi
    // değil. Fotoğraf yüklemek ve saklamak her planda çalışıyor
    // (`docs/122` Y6, `GuestRichMediaTest`).
    'site.plan.richMedia': 'Photographs of your dishes on the guest menu',

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
    /* Formun ÜSTÜNDE satıcının gerçek iletişim bilgisi (FF-216): kime
       yazdığını bilmeyen ziyaretçi yazmaz. */
    'site.contact.identity.heading': 'Who you are writing to',
    'site.contact.form.heading': 'Send us a message',

    // --- Hakkımızda (FF-216) ---------------------------------------------
    /*
        Ödeme kuruluşunun üye iş yeri incelemesi "satıcı kim?" sorusunu ayrı
        bir başlıkta arar. Bu sayfa hiçbir şey uydurmaz: olgular
        `CompanyProfile` üzerinden ortamdan gelir ve girilmemişse öyle yazar.

        ÜRÜN TANIMI ÖLÇÜLDÜ, ŞİŞİRİLMEDİ: burada yazan her yetenek depoda
        çalışır ve fiyat sayfasında satılır. "Türkiye'nin en iyisi",
        "binlerce restoran" gibi doğrulanamayan bir cümle yok.
    */
    'site.about.heading': 'About us',
    'site.about.lead':
        'Who sells Zabuno, what it is, how it is paid for and how to reach a person about it.',
    'site.about.seller.heading': 'The seller',
    'site.about.seller.body':
        'These are the details of the legal entity that sells the paid plans and is the seller named in the Distance Sales Agreement.',
    'site.about.service.heading': 'What Zabuno is',
    'site.about.service.body':
        'Zabuno is a subscription to a workspace in which a restaurant, cafe or bar keeps its menu and publishes it behind a permanent QR code. Changing a price or hiding a dish updates the page the guests see; the printed code stays the same.',
    'site.about.service.scope':
        'Depending on the plan, the workspace also covers branches, tables and QR codes, guest ordering, guest rating, team members with their own permissions, custom branding, reporting, and importing a menu from a photograph with a person confirming the result. The service is used through a web browser; nothing is installed.',
    'site.about.payment.heading': 'Payment methods we accept',
    /* SAĞLAYICI ÖLÇÜLDÜ (`IyzipayGateway`); KART MARKASI ÖLÇÜLEMEDİ ve bu
       yüzden yazılmadı. Bir logo tablosu, kabul edilmeyen bir kartı kabul
       ediliyor göstermek olurdu. */
    'site.about.payment.body':
        "Paid plans are paid by card, through the payment service provider Iyzico. We do not accept bank transfer, cash or payment on delivery for a subscription, and we never see or store your card number: it is entered on the provider's own pages. Which card brands the provider accepts is set in the provider's own configuration, so it is not listed here.",
    'site.about.reach.heading': 'How to reach us',
    'site.about.reach.body':
        'Write to us with the contact form or to the e-mail address above. We keep every message and reply to the address you give us.',
    'site.about.reach.cta': 'Open the contact form',
    'site.about.incomplete.heading': 'This page is not complete yet.',
    'site.about.incomplete.body':
        'The legal identity of the seller has not been published yet, so the details below are missing. Until they are entered, use the contact form to reach us.',

    /* Fiyatın yanında, ödeme adımından ÖNCE (FF-216). */
    'site.pricing.paymentMethods':
        'Paid plans are paid by card through the payment service provider Iyzico; no other payment method is offered.',
    'site.pricing.paymentMethods.cta': 'Read the Preliminary Information Form',
    /*
        REFERANS EKRANDA (FF-201, `docs/125`). Sekme kapanır, e-posta
        spam'e düşer; ekrandaki numara o an not alınabilir. `{reference}`
        denetleyicide doldurulur.
    */
    'site.contact.sentReference':
        'Your reference is {reference}. Quote it if you write to us again.',

    // --- Destek kanalı (FF-201, `docs/125`) --------------------------------
    /*
        YANIT TAAHHÜDÜ — TEK CÜMLE, TEK KAYNAK. İletişim sayfası, alındı
        e-postası ve panel bu anahtarı okur; `{hours}` sahibin
        `SUPPORT_RESPONSE_COMMITMENT_HOURS` kararıyla dolar. Değer yoksa
        cümle HİÇBİR yerde çizilmez — burada bir yedek cümle de yok.
    */
    'site.support.commitment': 'We reply within {hours} hours.',
    /*
        Gönderene ALINDI e-postası. Düz metin; her satır ayrı anahtar,
        çünkü iki satır (panel bağlantısı, cevap izni) yalnız bazı
        durumlarda yazılır ve çevirmen onları ayrı görmeli.
    */
    'site.support.ack.subject': 'Zabuno — we received your request {reference}',
    'site.support.ack.greeting': 'Hello {name},',
    'site.support.ack.received': 'We received your request and gave it the reference {reference}.',
    'site.support.ack.subjectLine': 'Subject: {subject}',
    'site.support.ack.keep': 'Keep this reference and quote it if you write to us again.',
    // Yalnız PANEL kanalında: kamu formundan yazan birinin paneli yoktur.
    'site.support.ack.panel':
        'You can follow the status of this request in your Zabuno panel, under Support.',
    // Yalnız `SUPPORT_EMAIL` yapılandırılmışsa: kimsenin okumadığı bir
    // kutuya cevap yazdırmak, hiç cevap istememekten kötü.
    'site.support.ack.reply': 'You can reply to this email to add more detail.',

    /*
        VERİ HAKKI BİLDİRİMİ (FF-226, `docs/138` §6).

        Cümleler KATALOGDA, Mailable'da değil: bu e-postada ne yazdığı
        sorusunun cevabı tek yerde durmalı ve sahibi onu bir gün PO
        dosyasından çevirebilmeli (`site.support.ack.*` ile aynı desen).

        E-POSTA BİR SÜRE TAAHHÜDÜ VERMEZ. "Şu kadar günde silinir" değil,
        "şu tarihte silinecek" der ve tarih kayıttan gelir.
    */
    'site.dataRights.greeting': 'Hello,',

    'site.dataRights.export.subject': 'Zabuno — your data archive is ready',
    'site.dataRights.export.body':
        'The archive you asked for is ready. It carries everything your workspace holds, in a machine readable form and as spreadsheet files a person can open.',
    'site.dataRights.export.detail': 'The link below stops working on {date}.',
    'site.dataRights.export.action': 'Download it here:',
    'site.dataRights.export.note':
        'If the link has expired, open Settings in your workspace and prepare a new archive.',

    'site.dataRights.erasure.subject': 'Zabuno — an erasure was requested for your workspace',
    'site.dataRights.erasure.body':
        'Someone with owner access asked for the data of your workspace to be erased. Nothing has been removed yet, and the workspace keeps working until the date below.',
    'site.dataRights.erasure.detail': 'The erasure will run on {date}.',
    'site.dataRights.erasure.note':
        'If this was not intended, open Settings in your workspace and take the request back. Records we must keep by law — issued invoices, the accounting entries behind them, the payments they rest on and the record of consents given — are not removed by an erasure; that screen lists each of them.',

    // --- Ana sayfadaki iki yeni cümle ------------------------------------
    // --- Ana sayfa gövdesi (`docs/100` Faz 2) -----------------------------
    /*
        Ana sayfanın 29 dizesi Blade'e gömülüydü ve
        `lang/untranslatable-debt.json` içinde borç olarak duruyordu: sahibi
        onları hiçbir PO dosyasından açıp çeviremiyordu, çünkü görecek satır
        yoktu. Bu tur o borcu kapatıyor.
    */
    'site.home.meta.title': 'Restaurant menu & workspace',
    'site.home.meta.description':
        "Zabuno gives your team a shared workspace to manage a restaurant's menu and catalog, publish it as a stable QR-linked page, and keep it updated as things change.",

    /*
        BAŞLIK KISALDI — ÖLÇÜLMÜŞ bir düzeltme (`docs/138` §4).

        Eski başlık ("Run your restaurant's menu and workspace from one
        place") 320 pikselde 28 punto ile DÖRT satırdı: 134 piksel. Üst
        çubuk (56) ve sahne dolgusuyla birlikte, iPhone 4'ün 480 piksellik
        görüntü alanının %40'ı başlığa gidiyordu ve giriş cümlesi
        katlanmanın altında kalıyordu.

        "workspace" da düştü. Bir kebapçı o kelimeyi bilmez; ürünün kendi
        `/urun/` sayfası aynı şeyi *"you write a menu, publish it, print a
        code for each table"* diye anlatıyor.
    */
    'site.home.hero.heading': "Run your restaurant's menu from one place",
    'site.home.hero.lead':
        'Write a menu, publish it, print a code for each table. Guests read it in the browser already on their phone.',
    'site.home.hero.actions.label': 'Account actions',
    'site.home.hero.openApp': 'Open the app',
    'site.home.hero.register': 'Create an account',
    /* Ücretsiz zincir bir VAAT değil, ölçülmüş bir olgu: kayıt, menü,
       yayın ve baskı `PlanCatalogueSeeder`da ücretsiz kademededir. */
    'site.home.hero.note': 'No plan needed to build a menu, publish it and print codes.',

    // --- Zincir: hesaptan masadaki koda (`ProductOverviewPage` HowItWorks) --
    /*
        ALTI ADIM, ÜRÜNÜN KENDİ SIRASI. Metin burada, OLGU
        `app/Infrastructure/Content/Pages/ProductOverviewPage.php` içinde:
        her adımın karşılığı olan denetleyici orada kayıtlı ve
        `HomeSceneContractTest` (HOME-REAL-07) ikisinin ayrışmasını kırar.
    */
    'site.home.chain.heading': 'From an empty account to a code on the table',
    'site.home.chain.lead':
        'Six steps, in this order. Every one of them works before you pay anything.',
    'site.home.chain.label': 'The six steps, side by side',
    'site.home.chain.step1.title': 'Create an account and a business',
    'site.home.chain.step1.body':
        'An email address, then the name, language, currency and time zone of your business.',
    'site.home.chain.step2.title': 'Add a branch',
    'site.home.chain.step2.body':
        'A branch has an address and its own clock. One restaurant is a business with one branch.',
    'site.home.chain.step3.title': 'Write the menu',
    'site.home.chain.step3.body':
        'Categories, dishes, prices, photos and declared allergens, in a draft guests cannot see.',
    'site.home.chain.step4.title': 'Publish a version',
    'site.home.chain.step4.body':
        'Publishing freezes a numbered snapshot. An earlier one can be restored.',
    'site.home.chain.step5.title': 'Print a code for each table',
    'site.home.chain.step5.body':
        'Export a card, a poster or a sheet to cut up, and repoint it later without reprinting.',
    'site.home.chain.step6.title': 'Guests scan',
    'site.home.chain.step6.body':
        'The code opens the menu in the browser already on the phone. No app, no account.',

    // --- Parçalar (`ProductOverviewPage` Capabilities) ---------------------
    /*
        ON İKİ PARÇA. Başlıkları ürünün kendi genel bakış sayfasındaki
        terimlerle BİREBİR aynıdır ve bir test bunu donduruyor: pazarlama
        metni ile ürün envanteri arasında ikinci bir gerçek kaynak
        doğamaz.
    */
    'site.home.parts.heading': 'What is in the product',
    'site.home.parts.lead':
        'Twelve parts of one account. Each has its own page where it is explained in full.',
    'site.home.parts.qrMenu.title': 'QR menu',
    'site.home.parts.qrMenu.body':
        'Photo, description, price, allergens and sold-out state, at a permanent address that fits on a card.',
    'site.home.parts.menuManagement.title': 'Menu management',
    'site.home.parts.menuManagement.body':
        'Categories, dishes, prices and stock, edited in a draft and released as numbered versions.',
    'site.home.parts.tables.title': 'Tables and QR codes',
    'site.home.parts.tables.body':
        'A code per table, read back by a decoder before it becomes a file, printed as vector cards.',
    'site.home.parts.branding.title': 'Design and branding',
    'site.home.parts.branding.body':
        'A logo and two colours, frozen into each publication so a menu never changes under a guest.',
    'site.home.parts.media.title': 'Images and media',
    'site.home.parts.media.body':
        'Photos are scanned, resized once and served small; the original is kept so copies can be remade.',
    'site.home.parts.languages.title': 'Languages and currency',
    'site.home.parts.languages.body':
        'The guest page follows the guest, dish names stay in your language, prices follow your currency.',
    'site.home.parts.branches.title': 'Multiple branches',
    'site.home.parts.branches.body':
        'Any number of branches under one business, each with its own menus, prices, codes and hours.',
    'site.home.parts.analytics.title': 'Analytics',
    'site.home.parts.analytics.body':
        'Scans, menu opens, dish views, searches that found nothing and orders sent, counted by the server.',
    'site.home.parts.ai.title': 'Zabuno AI',
    'site.home.parts.ai.body':
        'A photo of a printed menu becomes a draft you approve, and dishes typed twice are found.',
    'site.home.parts.ordering.title': 'Ordering from the table',
    'site.home.parts.ordering.body':
        'Where you switch it on, guests build a basket and send it to a kitchen screen.',
    'site.home.parts.team.title': 'A team with roles',
    'site.home.parts.team.body':
        'Editors, managers and a kitchen role that marks allergens and sold-out dishes and sees nothing else.',
    'site.home.parts.ratings.title': 'Ratings from the table',
    'site.home.parts.ratings.body':
        'A guest who scanned a code can rate a dish, and you can reply.',

    // --- Ne DEĞİL (`ProductOverviewPage` Limitations) ----------------------
    /*
        SINIRLAR SAYFADA, SÖZLEŞMEDE DEĞİL. Bir restoran sahibinin yanlış
        varsayımının bedeli SERVİS SIRASINDA ödenir — var sandığı bir
        özelliğin olmadığını, hiçbir şeyi değiştiremeyeceği saatte
        öğrenir. Bu bölüm o anı öne çeker.
    */
    'site.home.limits.heading': 'What Zabuno is not',
    'site.home.limits.lead':
        'Shorter than the parts list, and the part most software will not put on its home page.',
    'site.home.limits.till.title': 'Not a till and not a payment system',
    'site.home.limits.till.body':
        'Guests do not pay through Zabuno, and nothing here prints a bill or reports your takings.',
    'site.home.limits.reservations.title': 'No reservations, delivery or loyalty',
    'site.home.limits.reservations.body':
        'There is no table booking, no courier integration and no points card.',
    'site.home.limits.integrations.title': 'No third-party integrations',
    'site.home.limits.integrations.body':
        'Nothing connects to a till, an accounting package or a food marketplace. Your menu leaves and returns as a spreadsheet.',
    'site.home.limits.store.title': 'No app in a store',
    'site.home.limits.store.body':
        'There is no iPhone or Android app for you or your guests; both sides are web pages.',
    'site.home.limits.dishNames.title': 'Dish names are written once',
    'site.home.limits.dishNames.body':
        'The interface around the menu speaks two languages; the dishes are written in yours and are not translated.',
    'site.home.limits.oneBrand.title': 'One brand per account',
    'site.home.limits.oneBrand.body':
        'An account holds one business with many branches, not several businesses.',
    'site.home.limits.sectors.title': 'No sector editions',
    'site.home.limits.sectors.body':
        'No cafe version, bakery version or hotel version. One kind of business: places that serve food and drink.',

    'site.home.faq.heading': 'FAQ',
    'site.home.faq.what.question': 'What is Zabuno?',
    'site.home.faq.what.answer':
        "A workspace app for managing a restaurant's menu and catalog and publishing it to a stable QR-linked page.",
    'site.home.faq.account.question': 'Do I need an account to try it?',
    'site.home.faq.account.answer': 'Yes, create an account or log in to open the workspace app.',
    'site.home.faq.cost.question': 'What does it cost?',
    'site.home.faq.cost.answer':
        'Prices come from our plan catalogue, so what you read there is what we charge.',
    'site.home.faq.install.question': 'Do guests install anything?',
    'site.home.faq.install.answer':
        'No. The code opens a web page in the browser already on the phone.',
    'site.home.faq.pos.question': 'Is Zabuno a point-of-sale system?',
    'site.home.faq.pos.answer':
        'No. It shows the menu and can carry an order to the kitchen. It does not take payment and does not know your takings.',
    'site.home.contact.lead':
        'Ask about pricing, a pilot, or anything that is in your way. We keep every message; you get a confirmation on screen.',
    'site.home.contact.cta': 'Write to us',
    // --- YATIRIMCI İLİŞKİLERİ (FF-251) -----------------------------------
    /*
        DÖRT SAYFANIN METNİ. Buradaki hiçbir dize bir RAKAM taşımaz; taşıdığı
        şey `{parts}`, `{limits}`, `{sources}`, `{gates}` gibi yer
        tutuculardır ve onları `ShowInvestorPageController` ÖLÇÜLEN değerlerle
        doldurur (`InvestorDossier`).

        Kural bir üslup tercihi değil bir KAPI: `INVESTOR-HONEST-01` sayfadaki
        her rakamın dosyadan geldiğini ölçer. Buraya elle yazılmış tek bir
        sayı, o kapıyı kırar — ki maksat tam olarak budur.
    */
    'site.nav.investors': 'Investors',
    'site.investors.meta.title': 'Investor relations',
    'site.investors.meta.description':
        'What Zabuno is, which parts of it are built, which are deliberately not, what has been committed and what nobody has measured yet — with the file behind every claim.',
    'site.investors.heading': 'Investor relations',
    'site.investors.lead':
        'Zabuno is a working product before it is a business. These pages say what has been built, what has not, and what nobody has measured yet.',
    'site.investors.rules.heading': 'What is not on these pages',
    'site.investors.rules.body':
        'These pages carry no figure this repository cannot measure. How many places run the product, what it earns, how fast either moves, how large the market is and who else has praised it are counted nowhere in this code, so none of them is written here.',
    'site.investors.rules.body2':
        'What is here instead is the work itself: the product’s own inventory, the file behind each claim, the commitments deliberately left empty, and the gates that check them. A test breaks the build if a number nobody measured is ever added to these pages.',
    'site.investors.chain.heading': 'What the product does, in order',
    'site.investors.chain.lead':
        'The base journey is the same for everybody and costs nothing: an account, a business, a branch, a menu, a published version, a printed code. Plans add capability on top of that chain; they never switch the chain off.',
    'site.investors.chain.label': 'The product journey, step by step',
    'site.investors.built.heading': 'What is built',
    'site.investors.built.lead':
        'The product has {parts} parts, and every one of them is read from the product’s own inventory rather than written for this page. If a part is renamed or dropped, this list changes with it.',
    'site.investors.built.cta': 'Read the full inventory',
    'site.investors.limits.heading': 'What is not built',
    'site.investors.limits.lead':
        'There are {limits} things the product deliberately does not do. This is the section to read first: a capability someone assumed was there is discovered to be missing during service, when there is no time left to change anything.',
    'site.investors.commitment.heading': 'What has been committed, and what has not',
    'site.investors.commitment.open':
        'No availability figure is committed. The service level terms exist and describe how availability would be measured, excluded, notified and compensated — but the values themselves are deliberately empty, because nothing in this deployment measures uptime yet and a promise nobody can check is not a promise.',
    'site.investors.commitment.missingLabel': 'Empty on purpose, in config/sla.php:',
    'site.investors.commitment.set':
        'An availability figure has been committed. The service level terms carry it, together with how it is measured and what happens when it is missed.',
    'site.investors.commitment.cta': 'Read the service level terms',
    'site.investors.infrastructure.heading': 'Where it runs, and who else sees the data',
    'site.investors.infrastructure.lead':
        'This list of {subprocessors} entries is not written by hand. It is read from the hosting configuration, from the credential vault and from the measurement settings each time the page is drawn, so it cannot quietly go out of date.',
    'site.investors.infrastructure.unreadable':
        'The credential vault could not be read while this page was drawn, so this list may be short. “We could not look” is not the same sentence as “there is nothing”.',
    'site.investors.infrastructure.roleLabel': 'Role',
    'site.investors.infrastructure.dataLabel': 'Data it sees',
    'site.investors.infrastructure.locationLabel': 'Where it processes',
    'site.investors.verification.heading': 'How a claim is checked',
    'site.investors.verification.lead':
        '{sources} of the claims on these pages name the file in this deployment that produces them, and each of those files is looked for while the page is drawn. A claim whose file has gone is shown as missing rather than quietly kept.',
    'site.investors.verification.gates':
        'Gate scripts found in this deployment: {gates}. Each one is a check that runs against the real product rather than a description of it.',
    'site.investors.verification.present': 'Present',
    'site.investors.verification.missing': 'Not in this deployment',
    'site.investors.contact.heading': 'Talking to us',
    'site.investors.contact.lead':
        'Questions about what is built, what is not, and how a claim is checked are answered from the same inventory these pages are drawn from.',
    'site.investors.contact.cta': 'How to reach us',
    'site.investors.deckCta': 'Read the short version',

    /* `/investors/product` — envanterin kendisi. */
    'site.investors.product.meta.title': 'What has been built',
    'site.investors.product.meta.description':
        'The product inventory part by part, each part named together with the file in this deployment that produces it, and the seven things the product deliberately does not do.',
    'site.investors.product.heading': 'What has been built',
    'site.investors.product.lead':
        'The product’s own inventory, part by part, with the file behind each part. Nothing on this page was written for an investor; it is the same list the product describes itself with.',
    'site.investors.product.sourceLabel': 'Produced by',
    'site.investors.product.sourceMissing':
        'The file behind this claim is not in this deployment, so treat the claim as unproven.',
    'site.investors.product.sourceNone':
        'This entry names no file, because it describes something the product does not do — an absence has nothing to point at.',

    /* `/investors/deck` — aynı olgular, bir kez okunacak sırada. */
    'site.investors.deck.meta.title': 'The short version',
    'site.investors.deck.meta.description':
        'The same measured facts as the rest of this section, in the order you would read them once: what it is, what is built, what is not, what it costs, what is committed, where it runs, how it is checked and what nobody knows.',
    'site.investors.deck.heading': 'The short version',
    'site.investors.deck.lead':
        'The same facts as the rest of this section, in the order you would read them once. There is no file to download and no separate story: a deck that says something the product does not is a deck somebody has to correct later.',
    'site.investors.deck.evidenceLabel': 'Measured',
    'site.investors.deckWhat.heading': 'What it is',
    'site.investors.deckWhat.body':
        'One web product for places that serve food and drink: you write a menu, publish it, print a code for each table, and guests read the menu in the browser already on their phone. No app for the guest, no account for the guest.',
    'site.investors.deckWhat.evidence':
        'The journey has {chain} steps, and each step names the controller that runs it.',
    'site.investors.deckBuilt.heading': 'What is built',
    'site.investors.deckBuilt.body':
        'Menu, publication, printable codes, photos, branding, languages, branches, reports, ordering from the table, a team and ratings live in one account, not as separate products.',
    'site.investors.deckBuilt.evidence': '{parts} parts, each read from the product inventory.',
    'site.investors.deckNotBuilt.heading': 'What is not built',
    'site.investors.deckNotBuilt.body':
        'The product is not a point-of-sale system, does not take money from a guest, does not do reservations, has no third-party integrations and has no in-store app. Those are stated as plainly as the capabilities are.',
    'site.investors.deckNotBuilt.evidence':
        '{limits} stated limits, carried on the public home page as well as here.',
    'site.investors.deckPrice.heading': 'What it costs',
    'site.investors.deckPrice.body':
        'The base journey costs nothing and plans add capability on top of it. Every price shown on this site is read from the plan catalogue, never typed into a page.',
    'site.investors.deckPrice.evidence':
        'Prices are read from the plan catalogue and published at /pricing without an account.',
    'site.investors.deckCommitment.heading': 'What has been committed',
    'site.investors.deckCommitment.body':
        'The commercial commitments a buyer asks for — availability, incident notice, service credit — are written as terms but left without values, on purpose, until something measures them.',
    'site.investors.deckRuns.heading': 'Where it runs',
    'site.investors.deckRuns.body':
        'One virtual server carries the application, the database and the uploaded files. Everything else that touches customer data is named, and named only while it is actually switched on.',
    'site.investors.deckRuns.evidence':
        '{subprocessors} entries, read from configuration and the credential vault at render time.',
    'site.investors.deckChecked.heading': 'How it is checked',
    'site.investors.deckChecked.body':
        'Claims are tied to files and behaviour is tied to gates. A capability that disappears from the product cannot keep being sold on a page, because a red test says so before a reader does.',
    'site.investors.deckChecked.evidence':
        '{sources} claims tied to a file, {gates} gate scripts present.',
    'site.investors.deckUnknown.heading': 'What nobody knows yet',
    'site.investors.deckUnknown.body':
        'Demand, what a restaurant will pay, whether a restaurant keeps using it after the first month, and what it costs to serve one at scale. None of that is measured anywhere in this code, and this section does not pretend otherwise.',
    'site.investors.deckUnknown.evidence':
        'Not measured. The honest answer to an unmeasured question is that it is unmeasured.',

    /* `/investors/contact` — ikinci bir form altyapısı YOK. */
    'site.investors.contactPage.meta.title': 'Talking to us',
    'site.investors.contactPage.meta.description':
        'There is one contact route on this site and investors use the same one, together with the legal identity of the seller as this deployment has it configured.',
    'site.investors.contactPage.heading': 'Talking to us',
    'site.investors.contactPage.lead':
        'There is one contact route on this site and investors use the same one. A separate investor inbox has not been set up, and pretending otherwise would send your message to an address nobody reads.',
    'site.investors.contactPage.what.heading': 'What to send',
    'site.investors.contactPage.what.body':
        'Say who you are, what you are looking at and what you want to see. Questions about a capability are answered from the inventory these pages are drawn from, so the answer you get is the same answer the product gives itself.',
    'site.investors.contactPage.identity.heading': 'Who you would be talking to',
    'site.investors.contactPage.identity.body':
        'The identity below is read from this deployment’s own configuration. Where a field says it has not been provided, it has not been provided; an empty field is shown rather than hidden.',
    'site.investors.contactPage.commitment.absent':
        'No response time has been committed, so none is shown here. A fallback sentence such as “as soon as possible” would be a promise nobody made.',
    'site.investors.contactPage.cta': 'Open the contact form',
} as const;

export type SiteTranslationKey = keyof typeof siteTranslations;

export default siteTranslations;
