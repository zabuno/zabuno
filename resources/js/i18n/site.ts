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

    /*
        ═══ KİME UYGUN — FF-239 (`docs/139`, `docs/107` Faz 2.7) ═══

        "Pro" bir şey anlatmaz. Kademenin ADI, telefonundan bakan bir restoran
        sahibine hangisini alacağını söylemez; ölçüt onun KENDİ durumudur.

        ÜÇ CÜMLE DE UYDURULMADI, `PlanCatalogueSeeder`'ın kendi gerekçesinden
        türetildi (`docs/90`): ücretsiz kademe "zaten ücretsiz olan"ı
        adlandırır; `restaurant` "kırk masalık, sahip-işletmeli bir salonun ilk
        gün ihtiyacı"dır; `team` "sahibin menüyü tek başına yönetmediği yer".
        Yani sayfada okunan cümle, kademenin var olma sebebinin kendisidir —
        pazarlama için sonradan yazılmış ikinci bir gerekçe değil.

        Cümleler hakları TEKRAR SAYMAZ: "Adds" listesi zaten onu yapıyor ve
        aynı şeyi iki kez yazmak, dar ekranda kartın yarısını harcardı.

        Anahtar plan KODUNA bağlıdır ve tanınmayan bir kod hiç cümle üretmez
        (`SiteText::planAudienceLabel`) — sahibin panelden açtığı dördüncü bir
        plana uydurulmuş bir kitle yakıştırmak, tam olarak bu bölümün
        engellemek için var olduğu şey olurdu.
    */
    /*
        BÖLÜM BAŞLIKLARI. Fiyat sayfası artık dört bölümlü ve her bölümün
        görünür bir başlığı var: gizli bir başlık, ekran okuyucuya verilen
        hizmeti gören kullanıcıya vermez ve dar ekranda kaydıran birine
        nerede olduğunu söyleyen tek şey odur.
    */
    'site.pricing.plans.heading': 'The plans',
    'site.pricing.terms.heading': 'Paying, and stopping',
    'site.pricing.audience': 'Who it is for',
    'site.plan.audience.starter':
        'You are putting your menu behind a QR code for the first time, you look after it yourself, and you would rather not pay to find out whether it works.',
    'site.plan.audience.restaurant':
        'You run a room of tables yourself, and you want the page the guest reads to look like your restaurant rather than like a form.',
    'site.plan.audience.team':
        'You are not the only one who touches the menu: a manager, a waiter or an accountant needs an account of their own instead of yours.',

    /*
        ═══ NE DAHİL DEĞİL — HİÇBİR PLANDA ═══

        Bir fiyat sayfasının en pahalı sessizliği burasıdır. Tik dolu bir
        tablo, pahalı sütunun NEYE SAHİP olduğunu söyler; parasını ödedikten
        sonra masada hâlâ olmayacak şeyi söylemez.

        Altı satırın altısı da ölçülmüş bir yokluktur ve DİLİ ödünç alınmıştır,
        yeniden icat edilmemiştir: dördü sipariş sayfasının "What it does not
        do" bloğundan (`OrderingPage`), ikisi kurumsal fiyat sayfasının
        "What this page does not offer" bloğundan (`PricingPage`). Aynı
        yokluğu iki yüzeyde iki ayrı cümleyle anlatmak, ikisinden hangisinin
        doğru olduğunu bir gün bilinmez yapardı (`docs/137` §2a).

        Başlık "hiçbir plan" der, "ucuz plan" değil: bunlar bir kademe farkı
        değil, ürünün bugünkü sınırıdır.
    */
    'site.pricing.excluded.heading': 'What no plan includes',
    'site.pricing.excluded.lead':
        'These are missing from every plan, not only from the cheap one. They are written here rather than found out after paying.',
    'site.pricing.excluded.payment':
        'Guests do not pay through Zabuno. An order carries no card step and no bill; the money is still handled at the table, exactly as it is today.',
    'site.pricing.excluded.pos':
        'There is no connection to a till, a point-of-sale system or an accounting package. An order lives in Zabuno and is read on a Zabuno screen.',
    'site.pricing.excluded.delivery':
        'There is no takeaway, no delivery, no collection and no ordering before arriving. The one way to send an order is to scan the code on a table.',
    'site.pricing.excluded.kitchenHardware':
        'Nothing prints and nothing beeps. Orders appear on a screen that refreshes itself, so somebody has to be able to see that screen.',
    'site.pricing.excluded.campaign':
        'There is no trial period, no introductory rate, no annual discount and no campaign. A plan is a name, an amount and a set of rights.',
    'site.pricing.excluded.currency':
        'Prices are in Turkish lira only. There is no second currency and no conversion at the payment step.',

    /*
        ═══ SSS — SORULAR UYDURULMADI ═══

        Kaynak üç yerdir ve her sorunun karşılığı depoda ölçülebilir:

          · YARDIM MAKALESİ (`resources/help/en/first-15-minutes.blade.php`) —
            basılı kodun ölmemesi oradan gelir.
          · ÜRÜNÜN "NE DEĞİLDİR" LİSTELERİ (`PricingPage`, `OrderingPage`) —
            deneme süresi, şube başına fiyat, kişi başına fiyat.
          · YAYINLANMIŞ YASAL METİN (`RefundPolicy`, `docs/134`) — iptal, ne
            zaman yürürlüğe girdiği, iade, plan değiştirme, ödemesiz süre.

        DESTEK TALEBİ YÜZEYİ BİLEREK KULLANILMADI ve sebebi ölçüldü:
        `support_requests` bir konu taksonomisi taşımıyor (`workspace.support`
        kataloğunda tek bir serbest metin alanı var) ve depoda gerçek bir
        talep kütüğü yok. Oradan soru "türetmek", uydurmanın kaynak göstermiş
        hâli olurdu.

        Cevaplar ürünün BUGÜNKÜ davranışını anlatır; hiçbiri süre, oran ya da
        garanti sözü vermez. Ödemesiz sürenin gün sayısı da burada YAZMAZ —
        o bir yapılandırmadır (`billing.subscription.grace_days`) ve metne
        kopyalansaydı ilk değişiklikte ayrışırdı (`RefundPolicy` ile aynı
        gerekçe).
    */
    'site.pricing.faq.heading': 'Questions people ask before paying',
    'site.pricing.faq.stop.question': 'What happens to my menu if I stop paying?',
    'site.pricing.faq.stop.answer':
        'It keeps working. Writing the menu, publishing it, the printed codes and the page your guests read never needed a plan. When a paid period ends without a payment, the plan features stay on for a further period shown on your Billing screen, then switch off; your workspace, your menus and your published pages stay exactly as they are.',
    'site.pricing.faq.cancel.question': 'Can I cancel it myself?',
    'site.pricing.faq.cancel.answer':
        'Yes, from the Billing screen of your workspace. Cancelling stops the renewal: the plan stays active until the end of the period you have already paid for, and you can undo the cancellation from the same screen before that date without paying again.',
    'site.pricing.faq.refund.question':
        'Do I get money back if I cancel in the middle of a period?',
    'site.pricing.faq.refund.answer':
        'No. A period that has already started is not refunded, and you keep everything it included until it ends. The exceptions are a consumer exercising the statutory right of withdrawal, and us ending or reducing the service before a paid period is over; the Cancellation and Refund Policy states them.',
    'site.pricing.faq.change.question': 'How do I move to a bigger or a smaller plan?',
    'site.pricing.faq.change.answer':
        'From the same Billing screen. Moving up takes effect as soon as its payment succeeds. Moving down is scheduled for the end of the period you have paid for, and before you confirm it the screen names the capabilities the cheaper plan does not include.',
    'site.pricing.faq.trial.question': 'Is the free plan a trial that runs out?',
    'site.pricing.faq.trial.answer':
        'No, and there is no trial on the paid plans either. The free plan is the part of the product that never needed a plan, named rather than hidden: you can write the whole menu, publish it and print codes before deciding anything.',
    'site.pricing.faq.branch.question': 'Does a second branch or another waiter cost more?',
    'site.pricing.faq.branch.answer':
        'No. A plan carries one amount for the whole workspace, whatever it holds. Accounts for your team are a right the top plan opens, not a price per person.',
    'site.pricing.faq.reprint.question':
        'Do I have to reprint the codes if I change my prices or my plan?',
    'site.pricing.faq.reprint.answer':
        'No. Print once. You can change prices, reorganise the menu, publish a wrong list and go back to the previous one, or move where a code points; the paper on the table keeps working.',

    /*
        İPTAL VE İADE, FİYATIN YANINDA — ödeme yönteminin yanında (FF-216 ile
        aynı gerekçe). "Nasıl çıkarım?" sorusu tam burada sorulur; cevabını
        yalnız sözleşmenin içinde bırakmak, onu ödeme adımından SONRA
        öğrenmek demekti. Yasal metin `/refund-policy` adresinde yaşıyor ve
        bu satır onun yerine geçmez, ona götürür.
    */
    'site.pricing.cancellation':
        'You cancel from your own workspace, and the plan runs to the end of the period you paid for.',
    'site.pricing.cancellation.cta': 'Read the Cancellation and Refund Policy',

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
    'site.home.hero.heading': "Run your restaurant's menu and workspace from one place",
    'site.home.hero.lead':
        "Zabuno gives your team a shared workspace to manage a restaurant's menu and catalog, publish it as a stable QR-linked page, and keep it updated as things change.",
    'site.home.hero.actions.label': 'Account actions',
    'site.home.hero.openApp': 'Open workspace app',
    'site.home.features.heading': 'Features',
    'site.home.features.workspace.title': 'Restaurant & workspace context',
    'site.home.features.workspace.body':
        "Keep a restaurant's workspace, team, and settings organized in one tenant-scoped place.",
    'site.home.features.menu.title': 'Menu & catalog operations',
    'site.home.features.menu.body':
        'Create and edit menu items, categories, and catalog details from the workspace app.',
    'site.home.features.publication.title': 'Publication & stable QR',
    'site.home.features.publication.body':
        'Publish a menu to a stable, shareable page that a printed QR code can keep pointing to.',
    'site.home.features.media.title': 'Media intake & analytics',
    'site.home.features.media.body':
        'Media uploads go through quarantined media intake and review before they are available, alongside basic usage analytics for the published page.',
    'site.home.howItWorks.heading': 'How it works',
    'site.home.howItWorks.setup.title': 'Set up',
    'site.home.howItWorks.setup.body': 'complete your workspace and restaurant setup.',
    'site.home.howItWorks.build.title': 'Build the menu',
    'site.home.howItWorks.build.body':
        'add categories, items, prices, visibility, and allergens to your catalog.',
    'site.home.howItWorks.publish.title': 'Publish & get a QR',
    'site.home.howItWorks.publish.body': 'publish the menu to a stable page with a QR code.',
    'site.home.howItWorks.update.title': 'Update anytime',
    'site.home.howItWorks.update.body':
        'edit the menu and the published page and QR code stay the same.',
    'site.home.faq.heading': 'FAQ',
    'site.home.faq.what.question': 'What is Zabuno?',
    'site.home.faq.what.answer':
        "A workspace app for managing a restaurant's menu and catalog and publishing it to a stable QR-linked page.",
    'site.home.faq.account.question': 'Do I need an account to try it?',
    'site.home.faq.account.answer': 'Yes, create an account or log in to open the workspace app.',
    'site.home.faq.cost.question': 'What does it cost?',
    'site.home.faq.cost.answer':
        'Prices come from our plan catalogue, so what you read there is what we charge.',
    'site.home.contact.lead':
        'Ask about pricing, a pilot, or anything that is in your way. We keep every message; you get a confirmation on screen.',
    'site.home.contact.cta': 'Write to us',
} as const;

export type SiteTranslationKey = keyof typeof siteTranslations;

export default siteTranslations;
