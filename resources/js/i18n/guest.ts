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

    /*
        SEPET VE SİPARİŞ (FF-178) — `docs/115` S3.

        BU CÜMLELER YALNIZ SEPET ÇİZİLDİĞİNDE İNER. Sipariş alma kapalıysa,
        plan hakkı yoksa ya da karekod bir masaya bağlı değilse sunucu sepeti
        hiç çizmez ve bu sözlüğü de göndermez — olmayan bir ekranın sözlüğünü
        taşımak, misafirin hattından boşuna bayt yemektir.
    */
    'guest.cart.open': 'Cart',
    'guest.cart.title': 'Your cart',
    'guest.cart.close': 'Close the cart',
    'guest.cart.add': 'Add',
    // Ekleme SESSİZ OLMAZ: listenin dibindeki bir satıra ürün eklendiğinde
    // ekranda hiçbir şey değişmiyorsa misafir düğmeye tekrar basar.
    'guest.cart.added': '{name} was added to your cart.',
    'guest.cart.increase': 'One more',
    'guest.cart.decrease': 'One fewer',
    'guest.cart.remove': 'Remove from the cart',
    'guest.cart.count': 'Items in the cart: {count}',
    'guest.cart.total': 'Total',
    /*
        BOŞ SEPET BİR CÜMLEDİR, BOŞ BİR KUTU DEĞİL. Misafir sepeti açtığında
        neden boş olduğunu ve ne yapacağını okur; boş bir panel ona ürünün
        bozuk olduğunu düşündürür.
    */
    'guest.cart.empty':
        'Your cart is empty. Pick something from the menu and it will be listed here.',
    'guest.cart.submit': 'Send the order to the waiter',
    /*
        İKİ ONAY VARDIR VE MİSAFİR BUNU ÖNCEDEN OKUR (`docs/115` §2).

        Gönderdiği şey bir TALEPTİR; garson onayladığında bir İŞ olur. Bunu
        göndermeden önce söylemek, onaylanmayan bir siparişte misafirin
        aldatılmış hissetmesini engeller. Ödemenin masada alınması da burada
        yazar: bu üründe ödeme yoktur ve olmadığını saklamak, misafirin
        telefonda ödeme beklemesine yol açardı.
    */
    'guest.cart.submitNote':
        'The waiter checks and confirms the order before the kitchen starts. Payment is taken at the table.',
    'guest.cart.sending': 'Sending your order…',
    /*
        UYDURMA SÜRE YOK (M4). Kaç dakikada geleceğini bilmiyoruz; yanlış bir
        süre, misafiri tam da beklerken sinirlendirir (`docs/115` §8).
    */
    'guest.order.placed': 'Your order was received. The waiter will confirm it.',

    /*
        DÖRT RET, DÖRT CÜMLE (`docs/115` §7 S2).

        Her cümle iki şeyi birden söyler: SİPARİŞ GİTMEDİ (mutfakta hiçbir
        şey yok) ve ŞİMDİ NE YAPILIR. Tek bir "sipariş gönderilemedi",
        misafiri aynı düğmeye tekrar bastırır ve hangisini düzeltebileceğini
        asla öğretmez.
    */
    'guest.order.refused.outOfStock':
        '{name} ran out today, so nothing was sent. Remove it from the cart and send the rest.',
    'guest.order.refused.itemUnavailable':
        '{name} is no longer on the menu, so nothing was sent. Remove it from the cart and send the rest.',
    'guest.order.refused.orderingClosed':
        'The restaurant is not taking orders right now, so nothing was sent. Please tell the staff what you would like.',
    /*
        MASASIZ KOD — afiş, kartvizit ya da giriş kodu. Misafir kabahatli
        değildir ve cümle onu suçlamaz; yalnız masadaki kodun okutulmasını
        ister, çünkü siparişin düşeceği masa oradan gelir (M3).
    */
    'guest.order.refused.tableUnknown':
        'This code is not linked to a table, so nothing was sent. Please scan the code on your own table.',
    /*
        HAK YOKSA DÜRÜST CÜMLE, BOŞ EKRAN DEĞİL (Y3). Misafir restoranın
        planını düzeltemez; yapabileceği şey listeyi personele göstermektir
        ve cümle tam olarak onu söyler.
    */
    'guest.order.refused.entitlementRequired':
        'This restaurant does not take orders through the menu, so nothing was sent. You can show your list to the staff.',
    'guest.order.refused.tooManyOpenOrders':
        'This table already has several orders waiting, so this one was not sent. Please ask the staff first.',
    'guest.order.refused.tooManyLines':
        'There are too many different dishes in one order, so nothing was sent. Please send it in two orders.',
    'guest.order.refused.notSaved':
        'The order could not be saved, so nothing reached the kitchen. Please try again.',
    /*
        SON ÇARE CÜMLESİ DE BİR ŞEY SÖYLER. Bu ekranın üretemediği bir ret
        geldiğinde bile misafir iki şeyi öğrenir: mutfağa hiçbir şey gitmedi
        ve masadaki personel işi çözebilir.
    */
    'guest.order.refused.unknown':
        'The order was not sent and nothing reached the kitchen. Please tell the staff what you would like.',
    // Ağ koptuğunda "gitti mi gitmedi mi" belirsizliği MİSAFİRE YIKILMAZ:
    // sepet durur, cümle personele sormayı önerir.
    'guest.order.refused.offline':
        'Your order could not leave this phone. Your cart is still here — try again, or tell the staff.',

    'guest.pwa.install': 'Install the app',
    'guest.pwa.installAccepted': 'Installation accepted.',
    'guest.pwa.installDismissed': 'Installation dismissed.',
    'guest.pwa.installed': 'The app was installed.',
    'guest.pwa.offline': 'You are offline; showing the last menu you viewed.',

    /*
        FAVORİLER — CİHAZDA (`docs/114` Dalga 3, `docs/122` Y5).

        Misafir anonimdir ve öyle kalır: favori bu telefonda yaşar, sunucuya
        gitmez ve ziyaretçi anahtarına yazılmaz. Hesap istemek karekod
        menünün bütün vaadini bozardı; anahtara yazmak ise anahtar günlük
        döndüğü için favoriyi de günlük kaybederdi — kalıcı yapmak, takibi
        kalıcı yapmak demekti.

        İKİ CÜMLE, İKİ AYRI DURUM. Düğme basılıyken ne yapacağını söyler,
        basılı değilken de: tek bir "Favori" etiketi, ekran okuyucudaki
        misafire basmanın ne yapacağını hiç söylemezdi.
    */
    'guest.favourite.add': 'Save to favourites',
    'guest.favourite.remove': 'Remove from favourites',

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

    /*
        PUANLAMA (`docs/116` §3/§4) — misafirin oyu ve gördüğü puan.

        ═══ "HENÜZ YETERLİ DEĞERLENDİRME YOK" BİR CÜMLEDİR, BOŞLUK DEĞİL ═══

        Eşiğin altında ekran sıfır yıldız GÖSTERMEZ; sıfır bir ölçümdür ve
        bilinmeyenin yerine geçemez. Ama hiçbir şey yazmamak da bir cevap
        değildir: misafir "bu ürün puanlanmıyor mu, yoksa kötü mü?" diye
        sorar. Cümle o soruyu kapatır.

        ═══ SAYIYA BAĞLI ÇOĞUL YOK ═══

        Katalogda çoğul motoru yok (bkz. `guest.menu.summary` gerekçesi);
        bu yüzden "3 ratings" gibi sayıya bağlı hiçbir cümle burada yok.
    */
    'guest.rating.label': 'Rate this dish',
    /*
        Her yıldızın kendi erişilebilir adı. Ekran okuyucu kullanan misafir
        için "yıldız, yıldız, yıldız, yıldız, yıldız" beş özdeş düğmedir;
        kaçıncısına bastığını ancak bu cümle söyler.
    */
    'guest.rating.choice': '{score} out of {max}',
    'guest.rating.score': 'Rating: {score} out of {max}',
    'guest.rating.notEnough': 'Not enough ratings yet',
    /*
        MİSAFİRE VERİLEN SÖZ TAM OLARAK TUTULABİLİR OLMALI.

        "Puanınız eklendi" demiyoruz: oy ağırlıklandırmaya girmemiş olabilir
        (ani yığılma) ve o karar algoritmanındır. "Kaydedildi" ise her
        durumda doğrudur — sinyal deftere yazıldı ve orada duruyor.
    */
    'guest.rating.recorded': 'Thank you. Your rating was recorded.',
    'guest.rating.failed': 'Your rating could not be sent. Please try again.',
    'guest.rating.offline': 'You appear to be offline. Your rating was not sent.',
    /*
        KİM KONUŞUYOR, YAZILI (`docs/116` §5 D1 ile aynı ilke).

        Restoranın cümlesi ile misafirlerin ölçümü aynı satırın altında
        duruyor; kaynağı yazılmazsa misafir sahibin sözünü bir
        değerlendirme sanır.
    */
    'guest.rating.replyLabel': 'From the restaurant',
} as const;

export type GuestTranslationKey = keyof typeof guestTranslations;

export default guestTranslations;
