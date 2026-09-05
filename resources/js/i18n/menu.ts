import { createTranslator } from './translator';
import { overridesFor } from './generated-overrides';

const en = {
    'menu.loading': 'Loading menu…',
    'menu.status.saving': 'Saving…',
    'menu.initial.error.load': 'We could not load your menu. Please try again.',
    'menu.initial.error.retry': 'Retry',
    'menu.name.label': 'Menu name',
    'menu.name.error.required': 'Enter a menu name.',
    'menu.create.submit': 'Create menu',
    'menu.create.error.submit': 'We could not create the menu. Please try again.',
    'menu.categories.list.label': 'Menu categories',
    /*
        Ekranın sağ yarısı: RAYDA seçili olan kategorinin paneli. Rayla
        aynı adı taşıyamaz — biri "bütün kategoriler", öteki "şu an
        baktığın kategori"dir ve ekran okuyucu kullanan biri ikisi arasında
        gidip gelerek çalışır.
    */
    'menu.category.panel.label': 'Selected category',
    'menu.category.select.label': 'Category',
    'menu.category.name.label': 'Category name',
    'menu.category.create.submit': 'Add category',
    'menu.category.name.error.required': 'Enter a category name.',
    'menu.category.create.error.submit': 'We could not add the category. Please try again.',
    'menu.category.items.label': 'Items in {name}',
    'menu.category.add.disclose': 'Add category',
    /*
        RAYIN SONUNDAKİ EKLEME DÜĞMESİ — kaynakta metni yalnız "Kategori"
        (`panel.dc.html` satır 30260). Formun GÖNDER düğmesi hâlâ "Add
        category" der; ikisi aynı metni taşısaydı, ekranda aynı anda iki
        "Add category" düğmesi olur ve ekran okuyucu kullanan biri hangisinin
        formu açtığını, hangisinin kaydettiğini ayırt edemezdi.
    */
    'menu.category.add.short': 'Category',
    'menu.category.add.cancel': 'Cancel',
    'menu.product.name.label': 'Product name',
    'menu.product.create.submit': 'Add product',
    'menu.product.name.error.required': 'Enter a product name.',
    'menu.product.create.error.submit': 'We could not add the product. Please try again.',
    // Menüye ürün eklemenin TEK adımı. Öncesinde bu iş üç ayrı formdu:
    // ürün, fiyat, alerjen. Kullanıcı için hepsi tek bir iştir.
    // Ürün ekleme artık KATEGORİNİN İÇİNDE. Kategori bir alan değil,
    // tıkladığın yerdir — sahibinin tespitinin bir adım ötesi.
    'menu.entry.section.label': 'Add a product to {category}',
    'menu.entry.open': 'Add product',
    'menu.entry.cancel': 'Cancel',
    'menu.category.empty': 'No products in this category yet.',
    'menu.entry.category.label': 'Category',
    'menu.entry.category.empty': 'Add a category first — every product lives in one.',
    'menu.entry.allergens.disclose': 'Allergens (optional)',
    'menu.entry.submit': 'Add to menu',
    'menu.entry.error.submit': 'We could not add the product. Please try again.',
    'menu.entry.success': '{name} was added to {category}.',
    'menu.item.price.label': 'Price',
    'menu.item.currency.label': 'Currency',
    'menu.item.create.submit': 'Add item',
    'menu.item.price.error.required': 'Enter a price.',
    'menu.item.create.error.submit': 'We could not add the menu item. Please try again.',
    'menu.item.allergens.label': 'Allergens (comma-separated)',
    'menu.item.allergens.submit': 'Save allergens',
    'menu.item.allergens.error.submit': 'We could not update allergens. Please try again.',
    'menu.item.allergens.list.label': 'Allergens for {name}',
    // Görünen metin KISA, erişilebilir isim TAM. Ürün adını her butonun
    // içine basmak satırı okunamaz hâle getiriyordu ("Mercimek Çorbası
    // alerjenlerini düzenle" × her satır); ekran okuyucunun ihtiyaç
    // duyduğu bağlam ise `aria-label`'da korunur.
    'menu.item.allergens.edit.button': 'Edit allergens for {name}',
    'menu.item.price.edit.button': 'Edit price for {name}',
    'menu.item.allergens.edit.short': 'Allergens',
    'menu.item.price.edit.short': 'Price',
    // "Bugün tükendi" (`docs/82`) — GÖRÜNÜRLÜKTEN ayrı bir eksen. Gizli bir
    // ürün menüde yoktur; tükenmiş bir ürün menüde vardır ama bugün alınamaz.
    'menu.item.stock.out.short': 'Sold out',
    'menu.item.stock.back.short': 'Back in stock',
    'menu.item.stock.out.button': 'Mark {name} sold out for today',
    'menu.item.stock.back.button': 'Mark {name} available again',
    /*
        SATIRIN KENDİ ROZETİ (kanonik teslim paketi, `DESIGN_SPEC` §3).

        Durum önceden yalnız düğmenin metninden okunuyordu: "Back in stock"
        yazan bir düğme, ürünün ŞU AN tükenmiş olduğu anlamına geliyordu.
        Bu ters bir çıkarımdır ve listeye bakan kişi her satırda yeniden
        kurmak zorundaydı. Rozet durumu doğrudan söyler.
    */
    'menu.item.stock.badge': 'Sold out today',
    // KATEGORİ GENELİ (`docs/82` kriter 3, ekranı `docs/98` FF-64): "balıklar
    // bitti" altı ayrı tıklama olmamalı.
    'menu.category.stock.out.short': 'All sold out',
    'menu.category.stock.back.short': 'All available',
    'menu.category.stock.out.button': 'Mark everything in {name} sold out for today',
    'menu.category.stock.back.button': 'Mark everything in {name} available again',
    // Menüyü ALMAK ve GERİ KOYMAK (`docs/80`).
    // `docs/101` A5/A8 (FF-73): içe aktarma TEK kutuda; boş menüde açık.
    // `docs/101` Y3: kaydetmek masaya yansımaz; yayınlamak yansıtır.
    'menu.publishReminder.text': 'Saved. Guests still see the last published menu.',
    'menu.publishReminder.action': 'Publish now',
    'menu.publishReminder.dismiss': 'Later',
    /*
        EKRANIN EYLEM ŞERİDİ — kanonik kaynak `panel.dc.html` satır
        30204-30209. Dört eylem başlığın yanında tek sırada durur;
        öncesinde ilk ikisi kapalı bir `<details>` içindeydi ve sahip
        oradaki yolu hiç görmeden altmış ürünü elle yazıyordu.
    */
    'menu.actions.label': 'Menu actions',
    'menu.actions.csv': 'CSV',
    // Ürünün başka yerde zaten kullandığı sözcüklerle AYNI
    // (`workspace.menu.previewAndPublish`): aynı iş iki farklı adla
    // anılırsa sahip iki ayrı iş olduğunu sanır.
    'menu.actions.previewAndPublish': 'Preview & publish',
    /*
        MENÜ HAPLARI — kaynağın üç hapı (Ana menü · Kahvaltı 07–11 ·
        Ramazan kapalı). 2026-09-05'e kadar burada tek bir kimlik çipi
        vardı çünkü veri modeli şube başına TEK menü tutuyordu. Sahip o
        gün açıkça soruldu ve "çoklu menü YAPILSIN, saat bazlı geçişli"
        dedi (`docs/109` §7.1); kilit gevşetildi ve haplar doğdu.

        İpuçlarının hiçbiri uydurma değil: ad `menus.name`, durum
        `menus.state`, saat aralığı ise şubenin gerçek geçiş anlarından
        hesaplanır.
    */
    'menu.state.draft': 'Draft',
    'menu.state.published': 'Published',
    'menu.pills.label': 'Menus at this location',
    'menu.pills.add': 'New menu',
    'menu.pills.edit': 'Edit menu',
    'menu.pill.draft': 'draft',
    'menu.pill.disabled': 'closed',
    'menu.pill.allDay': 'all day',
    // "07:00–11:00 +1" — menü günün birden çok parçasını tutuyor.
    'menu.pill.moreWindows': '{range} +{count}',
    'menu.pill.servingNow': 'open now',
    'menu.pill.switching': 'Opening this menu…',
    'menu.window.startsAt.label': 'Starts at',
    'menu.window.endsAt.label': 'Ends at',
    /*
        Kural EKRANDA yazılıdır. Sahip "kahvaltı 07:00–11:00" der ve
        11:00'de ne olacağını bilmek zorundadır; bilmezse akşam menüsünün
        geri gelip gelmeyeceğini anlamak için misafiri bekler.
    */
    'menu.window.help':
        'At the end time, whichever menu covered that hour before comes back — so every hour of the day always has a menu. Leave both empty to keep this menu out of the rotation. A window may cross midnight (22:00 to 02:00).',
    'menu.window.error.incomplete': 'Enter both a start and an end time, or leave both empty.',
    'menu.window.error.submit': 'We could not save the service hours. Please try again.',
    'menu.window.disable': 'Close this menu',
    'menu.save.submit': 'Save menu',
    'menu.save.error.submit': 'We could not save the menu. Please try again.',
    'menu.delete.submit': 'Delete menu',
    'menu.delete.confirm': 'This removes the menu and everything in it.',
    'menu.delete.confirm.yes': 'Yes, delete it',
    'menu.delete.error.submit': 'We could not delete the menu. Please try again.',
    'menu.tools.summary': 'Bring in a whole menu',
    'menu.tools.help': 'From a photo of your printed menu, or from a CSV file.',
    'menu.empty.guide':
        'Start here: bring in your whole menu from a photo or a CSV below, or add a category and a product one at a time.',
    'menu.export.download': 'Download menu (CSV)',
    /*
        CSV bırakma alanı (FF-96). Öncesinde burada ham bir dosya girdisi
        vardı ve metnini tarayıcı işletim sisteminin dilinde yazıyordu.
    */
    'menu.import.dropzone.label': 'Drop a CSV file here, or choose one',
    'menu.import.dropzone.active': 'Release to import this file',
    'menu.import.dropzone.hint': 'CSV only',
    'menu.import.dropzone.choose': 'Choose a file',
    'menu.import.label': 'Import a CSV menu',
    'menu.import.help':
        'Columns: category, product, price, currency, allergens, description, visible. Nothing reaches guests until you publish.',
    'menu.import.done': 'Imported {items} items into {categories} new categories.',
    'menu.import.rejected': '{count} rows could not be read:',
    'menu.import.rejected.row': 'Line {line}: {reason}',
    'menu.import.error': 'The file could not be imported.',
    // SUNUM: açıklama ve fotoğraf tek düzenleyicide. Sahibin yaptığı iş
    // tektir — "bu ürünü misafire nasıl göstereceğim" — ve iki ayrı düğme
    // aynı satır için iki kez form açtırırdı.
    /*
        SATIRIN İLK SÜTUNU 48px'lik bir görsel karesidir ve fotoğrafsız
        üründe de durur. Kare aynı zamanda ayrıntıya giden kapıdır: fotoğraf
        eklemek için önce taşma menüsünü açmak, sorulmayan bir soruya cevap
        vermekti.
    */
    'menu.item.open.button': 'Open {name}',
    /*
        Boş kare yalnız GÖRSEL bir işarettir. Ekran okuyucu kullanan bir
        yönetici için hiçbir şey ifade etmez; eksiklik metinle de söylenir
        (`DESIGN_SPEC` §12 — durum asla yalnız renkle/şekille anlatılmaz).
    */
    'menu.item.meta.noPhoto': 'No photo',
    /*
        AÇIKLAMA EKSİĞİ SATIRDA DURUR — kaynağın ürün satırındaki `p.meta`
        alanının karşılığı (`panel.dc.html` satır 30269).

        Neden satırda, ayrıntı çekmecesinde değil: misafirin ürün adının
        altında okuyacağı cümle eksikse, sahip bunu ancak ürünü teker teker
        açarak öğrenebilirdi. Altmış ürünlü bir menüde bu altmış tıklama
        demektir; satırdaki tek kelime aynı işi bir bakışta yapar.
    */
    'menu.item.meta.noDescription': 'No description',
    'menu.item.presentation.edit.short': 'Photo & text',
    'menu.item.presentation.edit.button': 'Edit photo and description for {name}',
    'menu.item.presentation.submit': 'Save presentation',
    'menu.item.presentation.error.image':
        'The description was saved, but the photo was not attached.',
    'menu.item.description.label': 'Description',
    'menu.item.description.help':
        'A short line the guest reads under the name. Up to 500 characters.',
    // AI önerisi — `docs/97` Yolculuk B. Öneri bir taslaktır: kutuya
    // otomatik yazılır ama SAHİP onaylamadan (Kaydet'e basmadan) ürüne
    // geçmez; onaylamadan önce serbestçe düzenleyebilir.
    'menu.item.ai.description.request': 'Suggest with AI',
    'menu.item.ai.description.loading': 'Asking AI…',
    'menu.item.ai.description.suggested': 'AI suggestion — feel free to edit it before saving.',
    'menu.item.ai.description.suggested.fallback':
        'AI suggestion (from a backup provider) — feel free to edit it before saving.',
    'menu.item.ai.description.uncertain':
        'The AI was not confident about this one. Read it carefully before saving.',
    'menu.item.ai.description.unavailable': 'AI suggestions are not available right now.',
    'menu.item.ai.description.error': 'The AI suggestion failed. You can still write one yourself.',
    // AI KULLANILAMIYORSA — `docs/97` R9 / AIV-07. Eylem gösterilmez, YERİNE
    // sebebi yazılır: üç sebep üç FARKLI çözüme işaret eder (yönetici açar /
    // ay biter ya da bütçe artırılır / sağlayıcı anahtarı girilir). Tek bir
    // "kullanılamıyor" metni, sahibi kime gideceğini bilmeden bırakırdı.
    'menu.ai.unavailable.kill_switch': 'AI help is turned off right now.',
    'menu.ai.unavailable.budget_exhausted':
        'This month’s AI budget is used up. Everything else keeps working.',
    'menu.ai.unavailable.no_route': 'No AI provider is set up yet.',
    // OLASI TEKRARLAR — `docs/97` Yolculuk C. Salt okunur bir öneridir;
    // bir "birleştir" eylemi kasıtlı olarak yok (`docs/96` Faz 2 kapsamı).
    'menu.duplicates.heading': 'Possible duplicates ({count})',
    'menu.duplicates.help':
        'These product names look alike. Nothing is merged automatically — rename or remove one yourself if they really are the same.',
    'menu.duplicates.pair': '“{a}” and “{b}”',
    // FOTOĞRAFTAN İÇE AKTARMA (AI) — `docs/92`/`docs/97` Yolculuk A. Yükleme
    // Media sayfasında olur (`menuImportSource` slotu, yeniden icat
    // edilmez); burası yalnız OKUR ve İNCELETİR.
    // FF-75 toplu orkestra: 10'dan çok sayfa kuyruğa gider, ilerleme okunur.
    'menu.item.ai.import.batch.progress': 'Reading page {done} of {total}…',
    'menu.item.ai.import.batch.collected':
        '{rows} rows from {pages} pages. {dupes} duplicate rows were skipped.',
    'menu.item.ai.import.batch.failed': 'None of the pages could be read.',
    'menu.item.ai.import.disclose': 'Import from a photo (AI)',
    'menu.item.ai.import.cancel': 'Cancel',
    // TOPLU okuma (`docs/96` Faz 3): bir restoranın menüsü tek fotoğrafa
    // sığmaz. Dört sayfayı tek tek okutmak aynı işi dört kez yapmaktı.
    'menu.item.ai.import.media.label': 'Choose the photos to read',
    'menu.item.ai.import.photo.failed':
        '“{name}” could not be read — the other photos still went through.',
    'menu.item.ai.import.media.empty':
        'No processed photo is available yet. Upload one on the Media page (slot: Import source) first.',
    'menu.item.ai.import.read': 'Read these photos',
    'menu.item.ai.import.reading': 'Reading…',
    'menu.item.ai.import.unavailable': 'Reading menu photos is not available right now.',
    'menu.item.ai.import.error': 'The photo could not be read. You can still add items by hand.',
    'menu.item.ai.import.fallback': 'Read by a backup provider.',
    'menu.item.ai.import.preview.heading': 'What the AI read — review before adding',
    'menu.item.ai.import.row.price.missing': 'Price could not be read — this row will be skipped',
    'menu.item.ai.import.row.uncertain': 'Not confident about this one',
    'menu.item.ai.import.apply': 'Add these to the draft',
    'menu.item.ai.import.applying': 'Adding…',
    'menu.item.ai.import.rejected.row': 'Row {row}: {reason}',
    'menu.item.image.label': 'Photo',
    'menu.item.image.none': 'No photo',
    'menu.item.image.empty':
        'No processed photo is available yet. Upload one on the Media page first.',
    // Satır içi düzenleyicinin etiketi ÜRÜN ADINI taşır.
    //
    // Ekranda aynı anda iki "Price" alanı olabilir: aşağıdaki "menüye ürün
    // ekle" formundaki ve düzenlenen satırdaki. Aynı erişilebilir ismi
    // taşıyan iki kontrol, ekran okuyucu kullanan birine "Price" der ve
    // hangisi olduğunu söylemez.
    'menu.item.price.edit.label': 'Price — {name}',
    'menu.item.allergens.edit.label': 'Allergens — {name}',
    'menu.item.price.edit.submit': 'Save price',
    'menu.item.price.edit.error.submit': 'We could not update the price. Please try again.',
    'menu.item.visibility.checkbox.label': 'Show {name}',
    /*
        Görünürlük artık ETİKETSİZ BİR KUTU değil, menüde bir cümle (FF-102).
        Kutu, yanındaki "tükendi" düğmesiyle karışıyordu: ikisi de "misafir
        bunu görmüyor" anlamına geliyor ama biri bugünlük, diğeri kalıcıdır.
    */
    'menu.item.visibility.hide': 'Hide from the menu',
    'menu.item.visibility.show': 'Show on the menu',
    /*
        GÖRÜNÜRLÜK SATIRA GERİ DÖNDÜ — kanonik teslim paketi, `DESIGN_SPEC`
        §3 ("48×28px anahtar; açıkken brand").

        FF-102 onu taşma menüsüne almıştı ve gerekçesi doğruydu: ETİKETSİZ
        bir onay kutusu, yanındaki "tükendi" düğmesiyle karışıyordu. Ama
        çözüm yanlış yerdeydi. Kutuyu menüye saklamak durumu da sakladı:
        sahip on beş satırın hangisinin misafirde göründüğünü görmek için
        on beş menü açmak zorundaydı.

        Karışıklığın gerçek sebebi etiketsizlikti, satırda durması değil.
        Anahtar artık tam cümleyi taşıyor ("Show {name} on the menu") ve
        anahtar biçimi — kutu değil — kalıcı bir aç/kapa olduğunu söylüyor;
        "bugün bitti" ise ikonlu, anlık bir eylemdir.
    */
    'menu.item.visibility.switch.label': 'Show {name} on the menu',
    'menu.item.visibility.error.submit': 'We could not update visibility. Please try again.',
    'menu.category.order.label': 'Order for {name}',
    'menu.item.order.label': 'Order for {name}',
    /*
        Kategori rayı satırı: "sürükleme tutamacı + ad + sayı"
        (`DESIGN_SPEC` §3). Sayı, hangi kategorinin boş kaldığını listeyi
        açmadan gösterir.
    */
    'menu.category.count': '{count} products',
    /*
        Tutamaç GERÇEKTİR. Görünüp de çalışmayan bir tutamaç, kullanıcıya
        olmayan bir söz vermektir. Klavye/dokunmatik yolu kaldırılmadı:
        aynı işi yapan yukarı/aşağı düğmeleri satırda durmaya devam eder,
        çünkü sürükleme tek yol olsaydı klavyeyle çalışan bir yönetici
        menüsünü hiç sıralayamazdı.
    */
    'menu.category.reorder.handle': 'Drag {name} to reorder',
    /*
        Ürün ayrıntısı — masaüstünde SAĞDAN açılan çekmece. Sağ kenar,
        `DrawerPanel`'in soldan-açılma kuralının açıkça yazılmış
        istisnasıdır (denetçi paneli): sahip bir üründen diğerine geçerek
        çalışır ve soldaki liste ekranda kalmalıdır.
    */
    'menu.inspector.title': '{name} — details',

    // Menüyü İŞLETMEK (docs/73, P0-01): silme, ad düzeltme, sıralama.
    'menu.ops.error': 'That change could not be saved. Nothing was lost — try again.',
    'menu.item.delete.confirm':
        'Remove “{name}” from this menu? Already published versions keep it.',
    'menu.category.delete.confirm':
        'Remove “{name}” and its items from this menu? Already published versions keep them.',
    /* Satır içi düzenleme düğmeleri (FF-101). */
    /* Satır taşma menüsü (FF-101): silme, taşımanın yanından alındı. */
    /*
        Silme onayı (FF-101): tarayıcı kutusu yerine ürünün kendi diyaloğu.
        Başlık NEYİ sildiğimizi, gövde SONUCUNU söyler.
    */
    'menu.item.delete.title': 'Remove “{name}” from this menu?',
    'menu.item.delete.body':
        'Already published versions keep it — your guests see no change until you publish again. The draft row does not come back.',
    'menu.category.delete.title': 'Remove “{name}” and its products?',
    'menu.category.delete.body':
        'Already published versions keep them — your guests see no change until you publish again. The draft rows do not come back.',
    'menu.row.more': 'More actions for {name}',
    'menu.row.delete': 'Remove',
    'menu.rename.save': 'Save',
    'menu.rename.cancel': 'Cancel',
    'menu.rename.prompt': 'Correct the name',
    'menu.rename.error.empty': 'A name cannot be empty. Nothing was changed.',
    'menu.item.delete.label': 'Remove {name}',
    'menu.category.delete.label': 'Remove category {name}',
    'menu.rename.label': 'Rename {name}',
    'menu.move.up': 'Move {name} up',
    'menu.move.down': 'Move {name} down',
} as const;

type TranslationKey = keyof typeof en;

/**
 * Altı katalog wiring'i (CORE-08). `en` taban ve tip kaynağıdır; çeviriler
 * kısmi override'dır ve eksik anahtar `en`'e düşer. de/fr/ar/ru Stage 1'de
 * scaffold'dur (`docs/26` S1-WP03).
 */
export const t: (key: TranslationKey, vars?: Record<string, string>) => string = createTranslator(
    en,
    overridesFor('menu'),
);

/** Bu alanın İngilizce kaynak kataloğu — PO/MO/JSON zincirinin girdisi (CORE-08). */
export const menuTranslations: Record<string, string> = en;
