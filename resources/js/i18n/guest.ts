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
    'guest.menu.empty': 'This menu has no categories yet.',
    'guest.menu.category.empty': 'This category has no dishes yet.',

    'guest.search.label': 'Search the menu',
    'guest.search.placeholder': 'Type a dish name',
    'guest.search.noMatch': 'No dish matched.',
    'guest.search.matched': '{count} dishes matched.',

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
        BASILI KARTIN ÜSTÜNDEKİ CÜMLE (`docs/104` Döngü 8).

        Bu metin ekranda değil, masadaki kartta yaşar ve onu okuyan kişi
        misafirdir — bu yüzden misafir alanında durur ve restoranın diliyle
        çözülür. Kısa olmak zorunda: kartta 8 punto ve tek satır.
    */
    'guest.print.scanForMenu': 'Scan for the menu',
} as const;

export type GuestTranslationKey = keyof typeof guestTranslations;

export default guestTranslations;
