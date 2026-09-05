/**
 * MİSAFİR YÜZEYİ kataloğu — masadaki karekodu okutan kişinin okuduğu metin.
 *
 * `docs/82` ile açıldı, `docs/85` (P1-06) ile misafir sayfasının TAMAMINI
 * devraldı: artık şablonda tek bir sabit kullanıcı metni yok.
 *
 * KAYNAK DİL İNGİLİZCE — diğer bütün alanlarla aynı. İlk denemede kaynağı
 * Türkçe yapmıştım; boru hattı `en`'i kaynak saydığı için `tr` çevirisi boş
 * kalıyor ve İngilizceye geçen misafire de Türkçe gösteriliyordu. Yani dil
 * seçici çalışıyor gibi görünüp yalan söylerdi.
 *
 * Sayfanın bugüne kadar taşıdığı Türkçe cümleler `lang/po/guest.tr.po`
 * içine OLDUĞU GİBİ taşındı: bunlar çeviri değil, ürünün zaten sahip olduğu
 * metinler.
 */
export const guestTranslations = {
    // Tükendi METİNLE söylenir; yalnız renk ya da soluklukla anlatmak, renk
    // göremeyen misafir için hiçbir şey anlatmaz (WCAG 1.4.1).
    'guest.menu.item.soldOut': 'Sold out today',

    // Sayfa kendi kimliğini söyleyemiyorsa misafire hiç değilse ne baktığını
    // anlatır (`docs/79`).
    'guest.menu.subtitle': 'Published menu — showing the current published version.',
    /*
        SAYIYA BAĞLI ÇOĞUL YOK.

        "1 categories, 1 dishes" İngilizcede yanlıştır ve katalogda çoğul
        motoru yok. Bir tane eklemek, tek bir cümle için bütün dillere çoğul
        kuralı borcu getirirdi; etiket-değer biçimi her sayıda doğru okunur
        ve her dile aynı kolaylıkla çevrilir (`docs/86`).
    */
    'guest.menu.summary': 'Categories: {categories} · Dishes: {items}',
    'guest.menu.categories.label': 'Categories',
    /*
        ALERJEN BÖLÜMÜNÜN BAŞLIĞI (FF-175).

        Ürün sayfasında alerjenler bugüne kadar başlıksız bir çip listesiydi;
        ekran okuyucu onları bir bağlamı olmayan üç kelime olarak okuyordu.

        CÜMLE BİR TAMLIK İDDİASI TAŞIMAZ ve taşımamalı: "alerjen bilgisi"
        der, "bunlar dışında alerjen yoktur" DEMEZ. Yanlış bir alerjensizlik
        iddiası bir sağlık olayıdır ve `ArtifactSchemaValidator` bu yüzden
        `allergen_free` gibi alan adlarını ada göre reddediyor.
    */
    'guest.menu.allergens.label': 'Allergen information',
    'guest.menu.empty': 'This menu has no categories yet.',
    'guest.menu.category.empty': 'This category has no dishes yet.',

    'guest.search.label': 'Search the menu',
    'guest.search.placeholder': 'Type a dish name',
    'guest.search.noMatch': 'No dish matched.',
    'guest.search.matched': '{count} dishes matched.',

    /*
        SESLİ ARAMA (FF-177) — TARAYICIDA BAŞLAR, TARAYICIDA BİTER.

        Kayıt sunucuya GİTMEZ: ses kişisel veridir ve onu taşımak,
        çözdüğünden çok sorun getirirdi. Tarayıcının kendi tanıyıcısı metni
        üretir, ürün metni arar.

        Düğme yalnız tarayıcı konuşma tanımayı destekliyorsa çizilir; bu
        yüzden aşağıdaki cümlelerin hiçbiri "tarayıcın desteklemiyor" demez.
        Desteklemeyen tarayıcıda söylenecek bir şey yoktur, çünkü misafire
        hiçbir söz verilmemiştir.
    */
    'guest.voice.label': 'Search by voice',
    'guest.voice.listening': 'Listening… say a dish name.',
    /*
        REDDEDİLEN İZİN SESSİZ GEÇMEZ. Misafir düğmeye basar, hiçbir şey
        olmaz ve nedenini bilmezse ürün bozuk görünür. Cümle suçlamaz ve
        yazarak aramanın hâlâ açık olduğunu söyler.
    */
    'guest.voice.denied':
        'Microphone permission was not given, so voice search cannot run. You can still type.',
    'guest.voice.error': 'Voice search could not run this time. You can still type.',

    // FİLTRELER (FF-177) — kategori rayı zaten var; bu eksenler alerjen ve
    // fiyattır.
    'guest.filters.label': 'Filters',
    'guest.filters.clear': 'Clear filters',
    'guest.filters.allergenExclude': 'Exclude allergens',
    /*
        BU CÜMLE BİR GÜVENLİK KARARIDIR VE KISALTILAMAZ (`docs/114` §0).

        Filtre yalnız HARİÇ TUTAR. Ürün "bu üründe fıstık yoktur" diyemez;
        söyleyebileceği tek şey "restoran fıstık bildirmedi"dir. Cümle
        olmasaydı boşalan liste, misafirin kalan ürünleri güvenli sanmasına
        yol açardı — ve yanlış bir alerjensizlik iddiası bir sağlık olayıdır.
    */
    'guest.filters.allergenHint':
        'Dishes the restaurant declared with that allergen are removed. A dish with no declaration is not a dish known to be free of it — please ask the staff.',
    'guest.filters.priceRange': 'Price range',
    'guest.filters.priceMin': 'At least',
    'guest.filters.priceMax': 'At most',
    /*
        SIFIR SONUÇ "BÖYLE BİR ŞEY YOK" DEMEK DEĞİLDİR. Aramadaki boşluk
        menüde olmayan bir şeyi anlatır; filtredeki boşluk yalnız misafirin
        kendi koyduğu sınırı anlatır ve menü doludur.
    */
    'guest.filters.noMatch':
        'No dish fits your filters. The menu has other dishes; try removing one filter.',
    'guest.filters.matched': '{count} dishes shown.',

    'guest.pwa.install': 'Install the app',
    'guest.pwa.installAccepted': 'Installation accepted.',
    'guest.pwa.installDismissed': 'Installation dismissed.',
    'guest.pwa.installed': 'The app was installed.',
    'guest.pwa.offline': 'You are offline; showing the last menu you viewed.',

    // DİL SEÇİMİ (`docs/85`, P1-06).
    'guest.language.label': 'Language',
    /*
        İÇERİK çevirisi ARAYÜZ çevirisi değildir.

        Ürün adlarını restoran kendi dilinde yazar ve onları çevirmiyoruz.
        Arayüzü İngilizceye alan bir misafire menünün de İngilizce olacağını
        ima etmek, tutulmayacak bir söz vermek olurdu.
    */
    'guest.language.contentNotice': 'Dish names are in the restaurant’s own language.',

    /*
        ÇIKMAZ SOKAK (`QR-PUBLIC-404-UNIFORM-01`). Kodu tarayan kişi bir
        restoran masasında oturuyor; ona ham bir 404 göstermek ürünü bozuk
        gösterirdi. Metin BİLEREK tek bir durumu anlatmaz: bilinmeyen, bozuk
        ve devre dışı kod aynı cümleyi görür.

        Bu üç dize şablona gömülüydü ve sahibi onları hiçbir PO dosyasından
        çeviremiyordu (FF-98).
    */
    'guest.deadEnd.title': 'Menu not found',
    'guest.deadEnd.heading': 'This menu cannot be shown right now',
    'guest.deadEnd.body':
        'The QR code is not linked to a menu. Please tell the restaurant staff; they can get you the current menu.',
    /*
        SERVİS DIŞI SAAT (FF-139) — ÇIKMAZ SOKAKTAN AYRI CÜMLELER.

        Sahip bir gece menüsü tanımlayıp saatini verebilir ama içeriğini
        yayınlamamış olabilir. O saatte misafire "menü bulunamadı" demek
        yalandır: menü duruyor, o saatte servis edilmiyor. Boş bir menü
        göstermek daha da kötüdür — restoranın menüsünü sildiğini sandırır.

        Metin bir SÖZ VERMEZ: restoranın açık olup olmadığını bilmiyoruz ve
        bilmediğimizi yazmayız. Yalnız ekranda olanı ve personele
        sorulabileceğini söyler.
    */
    'guest.outOfService.title': 'Menu is out of service',
    'guest.outOfService.heading': 'No menu is being served at this hour',
    'guest.outOfService.body':
        'This menu is not being served right now. Please ask the restaurant staff; they can tell you what is available.',
    /*
        SAAT GERÇEK VERİDİR. Sahibin kendi yazdığı geçişlerden okunur ve
        gösterilebilir bir sonraki menü yoksa bu cümle HİÇ kurulmaz. Tahmini
        bir saat yazmak, tutulmayacak bir söz vermek olurdu.
    */
    'guest.outOfService.nextService': 'Next service starts at {clock}.',

    /*
        ŞUBE KAPALIYKEN (FF-141) — SERVİS DIŞI DEĞİL, MENÜNÜN ÜSTÜNDEKİ ŞERİT.

        Servis dışı sayfası "gösterilecek menü yok" der ve menüyü hiç çizmez.
        Bu şerit ise menü çizilirken durur: gece 23:00'te karekodu okutan
        misafir çoğu zaman yarını planlıyordur ve menüyü ondan saklamak ona
        hizmet etmez.

        DURUM YALNIZ RENKLE ANLATILMAZ. Şerit kırmızı da olsa, cümlenin
        kendisi "kapalıyız" demek zorunda: rengi göremeyen misafir için renk
        hiçbir şey anlatmaz (WCAG 1.4.1) — aynı gerekçe "tükendi" etiketinde
        de yazılı.
    */
    'guest.closed.notice': 'We are closed right now.',
    /*
        AÇILIŞ SAATİ GERÇEK VERİDİR: şubenin kendi haftasından okunur ve
        şubenin kendi saat dilimindedir. Hafta girilmemişse ya da yedi günü
        de kapalıysa bu cümle HİÇ kurulmaz — tahmini bir saat ya da gün adı
        yazmak, tutulmayacak bir söz vermek olurdu.

        "Bugün" ile gün adı AYRI cümlelerdir: masadaki misafir için "bugün
        09:00" ile "Pazartesi 09:00" apayrı iki bilgidir ve tek bir kalıba
        sıkıştırılırsa biri mutlaka tuhaf okunur.
    */
    'guest.closed.opensToday': 'We open today at {clock}.',
    'guest.closed.opensOn': 'We open on {day} at {clock}.',
    /*
        GÜN ADLARI KATALOGDA YAŞAR. Sunucuda `date()` ile üretilselerdi
        sunucunun diline bağlı olurlardı; misafirin dili ise onun kendi
        seçimidir (`docs/85`). ISO-8601 sırası: 1 = Pazartesi … 7 = Pazar.
    */
    'guest.day.1': 'Monday',
    'guest.day.2': 'Tuesday',
    'guest.day.3': 'Wednesday',
    'guest.day.4': 'Thursday',
    'guest.day.5': 'Friday',
    'guest.day.6': 'Saturday',
    'guest.day.7': 'Sunday',

    /*
        BASILI KARTIN ÜSTÜNDEKİ CÜMLE (`docs/104` Döngü 8).

        Bu metin ekranda değil, masadaki kartta yaşar ve onu okuyan kişi
        misafirdir — bu yüzden misafir alanında durur ve restoranın diliyle
        çözülür. Kısa olmak zorunda: kartta 8 punto ve tek satır.
    */
    'guest.print.scanForMenu': 'Scan for the menu',
} as const;

export type GuestTranslationKey = keyof typeof guestTranslations;

export default guestTranslations;
