<?php

declare(strict_types=1);

use App\Support\Localization\Negotiation\BrowserResolver;
use App\Support\Localization\Negotiation\ExplicitChoiceResolver;
use App\Support\Localization\Negotiation\PathPrefixResolver;
use App\Support\Localization\Negotiation\QueryParameterResolver;
use App\Support\Localization\Negotiation\RegionResolver;
use App\Support\Localization\Negotiation\SourceLanguageResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Yayınlanan arayüz dilleri
    |--------------------------------------------------------------------------
    |
    | Kullanıcıya SUNULAN dillerin tek kaynağı. Katalogların derlenmiş
    | olması bir dilin sunulduğu anlamına gelmez: bugün altı katalog
    | derleniyor ama yalnız bu listedeki diller ürüne girer.
    |
    | Owner kararı (2026-09-08): İngilizce kaynak ve varsayılan, Türkçe ikinci
    | arayüz dilidir. Katalog ve katalog dışı gövdelerin kabulü birlikte
    | gerekir; bu aday gövde paketleriyle birlikte yayımlanmalıdır.
    |
    | Sebebi gözle görüldü: uygulama `APP_LOCALE=tr` ile çalışırken `menu`
    | alanı çevriliydi, `workspace` alanı değildi. Ekranda "Kategori adı"
    | ile "Build and edit the categories…" yan yana duruyordu. Yarım çeviri,
    | çevirisizlikten kötü görünür — çünkü çevirisizlik tutarlıdır.
    |
    | Bu listeye bir dil eklemek, o dilin kataloğunun TAM olmasını gerektirir;
    | `ShippedLocalesAreCompleteTest` bunu zorlar.
    |
    */

    'shipped_locales' => ['en', 'tr'],

    /*
     * TÜRKÇE 2026-09-05'te LİSTEYE GİRDİ ve bunun tek şartı vardı: katalog
     * TAM olacak. Girdiği gün 1997 metnin sıfırı boştu.
     *
     * Buraya girmeden önce durum şuydu: Türkçe kataloğun yarısı çevrilmişti
     * (`workspace` alanında 1324 metnin 587'si boştu) ve o hâlde listeye
     * eklenseydi, ekranda "Kategori adı" ile "Build and edit the
     * categories…" yan yana dururdu. Yarım çeviri çevirisizlikten kötü
     * görünür — çünkü çevirisizlik en azından tutarlıdır.
     *
     * Kayda değer olan şu: çeviriler tamamlandığı gün bu satır
     * güncellenmedi. Yani 658 metin çevrildi ve hiçbiri kullanıcıya
     * ulaşmadı; ürün Türk restoran sahibine İngilizce konuşmaya devam etti.
     * İşi bitiren adım, işi görünür kılan adım değildir.
     *
     * ═══ VE SONRA GERİ ALINDI — SAHİBİN KARARI, 2026-09-05 ═══
     *
     * "Ben söylemedikçe tercüme çeviri yapma. İngilizce kalsın."
     *
     * Bu listenin şartı (katalog TAM olacak) bir kapıyla zorlanıyor. Türkçe
     * listede kaldığı sürece o kapı, EKLENEN HER YENİ METİN için çeviri
     * yapılmasını zorunlu kılar — yani tam olarak yapılmaması istenen iş.
     * Yeni bir metnin çevirisiz kalması kapıyı kırardı; kapıyı gevşetmek ise
     * yarım çeviriyi serbest bırakırdı ve yarım çeviri çevirisizlikten kötü
     * görünür.
     *
     * Karar bu yüzden tek yönlü uygulandı: Türkçe listeden çıktı, yeni
     * metinler İngilizce kalıyor, kapı geçiyor.
     *
     * ÇEVİRİLER SİLİNMEDİ. `lang/po/*.tr.po` içindeki 1997 metin olduğu gibi
     * duruyor ve TAMDIR; sahip istediği gün bu listeye 'tr' eklemek yeterli.
     * Silmek, bir daha yapılması gereken bir işi geri getirirdi.
     *
     * ═══ VE SONRA GERİ AÇILDI — 2026-09-08, #352 ═══
     *
     * YUKARIDAKİ İKİ PARAGRAF ARTIK BU DOSYANIN DEĞERİNİ ANLATMIYOR ve bu
     * satır tam olarak o yüzden var. `shipped_locales` bugün `['en', 'tr']`
     * — yani "Türkçe listeden çıktı" cümlesi 2026-09-08'den beri YANLIŞTI ve
     * üç gün boyunca kimse fark etmedi.
     *
     * Ölçülen kayıt şu: `7b42c5b8` ("Redesign desktop frontpages and enable
     * English/Turkish language journeys", #352, 2026-09-08) satırı `['en']`
     * → `['en', 'tr']` yaptı ve BU GEREKÇE BLOĞUNA DOKUNMADI. Değer değişti,
     * değerin sebebi değişmedi. Bugün Türkçe tarayıcı gerçekten
     * `<html lang="tr">` alıyor.
     *
     * NEDEN ÖNEMLİ: bu blok sahibin bir TALİMATININ kaydıdır ("Ben
     * söylemedikçe tercüme çeviri yapma"). Bir talimat kaydı, yürürlükten
     * kalktığında susarsa, onu okuyan bir sonraki oturum yürürlükte sanır ve
     * ya yanlış işi yapar ya doğru işi reddeder. Yapılandırma dosyasının
     * yorumu, kodun kendisi kadar bakım ister.
     *
     * ═══ HANGİ KARAR YÜRÜRLÜKTE: 09-08, 09-05 DEĞİL ═══
     *
     * Bu dosya iki tarihli owner kararı taşıyor ve ikisi zıt yönde:
     *
     *   2026-09-05 — "Ben söylemedikçe tercüme çeviri yapma." (yukarıda)
     *   2026-09-08 — "İngilizce kaynak ve varsayılan, Türkçe ikinci arayüz
     *                 dilidir." (bu dosyanın BAŞLIĞINDA, 'Yayınlanan arayüz
     *                 dilleri' bloğunda)
     *
     * SONRAKİ TARİH YÜRÜRLÜKTEDİR: 09-08 kararı 09-05 kararını değiştirir ve
     * `['en', 'tr']` değeri o karara dayanır — uydurulmuş değildir. Yukarıdaki
     * 09-05 bloğu SİLİNMEDİ çünkü bir kararın ne zaman ve hangi gerekçeyle
     * değiştiği de kayıttır; ama artık yürürlükte olan o değil.
     *
     * Almanca ve Fransızca katalogları YAZILDI (`lang/po/*.de.po`,
     * `*.fr.po`) ama bu listede DEĞİL: yayında iki dil var, `en` ve `tr`.
     * Derlenmiş katalog, sunulan dil demek değildir — bu dosyanın başlığındaki
     * ilk cümle tam olarak bunu söylüyor.
     */

    /*
    |--------------------------------------------------------------------------
    | Kaynak dil
    |--------------------------------------------------------------------------
    |
    | Kodda yazılan her dize bu dildedir ve bu katalog her zaman eksiksiz
    | olmak zorundadır (`I18N-SIX-CATALOGS-10`).
    |
    */

    'source_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Altyapının TANIDIĞI diller — dokuz
    |--------------------------------------------------------------------------
    |
    | Sahibin kararı (2026-09-05, `docs/120` §1-2): *"Ana dili İngilizce,
    | Türkçe opsiyonel, ayrıca (daha sonra) tercümesi yapılacak diller
    | (bugünden itibaren altyapı çok dili desteklemeli)."*
    |
    | BU LİSTE `shipped_locales` DEĞİLDİR ve onun yerine geçmez. İkisi ayrı
    | soruları yanıtlar:
    |
    |   supported_locales — "bu dili TARİF edebiliyor muyuz?"
    |                       Kütük satır taşıyabilir mi, adres uzayı var mı,
    |                       yön doğru mu, hreflang ilan edilebilir mi.
    |
    |   shipped_locales   — "bu dilde EKSİKSİZ bir ürün verebiliyor muyuz?"
    |                       Kullanıcıya sunulan tek liste budur ve bugün
    |                       `en` ile `tr` var (2026-09-08, #352 — gerekçesi
    |                       ve hâlâ açık olan onayı yukarıdaki blokta).
    |
    | Ayrım kâğıt üstünde değil: bir dil buraya girdiği gün ürünün o dilde
    | tek kelimesi çevrilmez. `shipped_locales`e girmesi kataloğunun TAM
    | olmasını ve sahibin `ÇEVİRİLERE BAŞLA` demesini gerektirir.
    |
    | Dokuz dilin BUGÜN SATIRI ÜRETİLMEZ. Kütükte dokuz dilin 386 sayfası
    | üç binden fazla, hiçbiri yazılmayacak satır demekti; yapı dokuzu
    | taşır, ürün bugün ikisini kullanır (`ContentPageIdentityTest`).
    |
    | Sıra rastgele değil: kaynak dil önce, sonra kataloğu tam olan tek dil,
    | sonra sahibin saydığı sıra. `ku` Kurmancî'dir (Latin yazı, LTR);
    | Soranî (`ckb`, Arap yazısı, RTL) AYRI bir dildir ve gerekirse ayrı
    | eklenir — ikisini tek koda sıkıştırmak, birini yanlış yazı sistemiyle
    | göstermek olurdu (`docs/120` §8).
    |
    | Endonim, bayrak/bölge işareti ve dil değiştirici sözleşmesi bu paketin
    | DIŞINDA (`docs/120` §5-6): burada yalnız kütüğün ve adres uzayının
    | tanıdığı kod listesi var.
    |
    */

    'supported_locales' => ['en', 'tr', 'ar', 'ru', 'fa', 'ku', 'de', 'fr', 'it'],

    /*
    |--------------------------------------------------------------------------
    | Ağırlıklı dil tespit zinciri
    |--------------------------------------------------------------------------
    |
    | `docs/120` §4. Sahibin yönlendirmesi: "Drupal'da bahsettiğim ağırlık
    | vardı ve dil değiştirici buna göre otonom çalışıyordu."
    |
    | AĞIRLIK BURADA YAŞAR, KODDA DEĞİL. Sıra değişince `LanguageNegotiator`
    | değişmez — bir sıralama denemesi bir dağıtım değil, bir ayardır. Bu,
    | Drupal'ın kararının asıl değeri.
    |
    | Küçük ağırlık ÖNCE konuşur. Sayılar `docs/120` §4.2 tablosundan birebir
    | alınmıştır ve aralarında boşluk bırakılmıştır: yeni bir yöntem, mevcut
    | hiçbirini yeniden numaralandırmadan araya girebilsin diye.
    |
    */

    'negotiation' => [

        'methods' => [

            /*
             * −20 · AÇIK SEÇİM. En ağır olan bu: Almanya'da yaşayan bir Türk
             * tarayıcısı Almanca olsa da Türkçe okumak isteyebilir ve bir kez
             * seçtiyse bir daha sorgulanmamalı.
             */
            'explicit' => [
                'weight' => -20,
                'resolver' => ExplicitChoiceResolver::class,
                'options' => ['cookie' => 'zbn_language'],
            ],

            /*
             * −10 · ADRES ÖNEKİ. İçerik ve URL dilinin kaynağı. Arayüz
             * zincirinde KASTEN yok: kurumsal bir Türkçe sayfayı okumak,
             * ürün panelinin dilini değiştirmemeli.
             */
            'path' => [
                'weight' => -10,
                'resolver' => PathPrefixResolver::class,
            ],

            /*
             * 0 · OTURUM PARAMETRESİ. Önizleme ve paylaşım için. Açık
             * seçimden hafif, çünkü paylaşılan bir bağlantı kullanıcının
             * kendi kalıcı tercihini ezmemeli.
             */
            'session' => [
                'weight' => 0,
                'resolver' => QueryParameterResolver::class,
                'options' => ['parameter' => 'language'],
            ],

            /*
             * 10 · TARAYICI VE CİHAZ.
             */
            'browser' => [
                'weight' => 10,
                'resolver' => BrowserResolver::class,
            ],

            /*
             * 20 · BÖLGE. Dili SEÇMEZ, belirsizliği ÇÖZER — bu yüzden
             * tarayıcıdan sonra gelir. Tablo kısadır ve kısa kalmalı: yalnız
             * gerçekten tek bir baskın dile işaret eden saat dilimleri.
             * Belirsiz bir bölgeye dil atamak, belirsizliği çözmek değil
             * gizlemektir.
             *
             * Saat dilimi sunucuda bilinmez; tarayıcı `Intl…timeZone` ile
             * çereze yazar. IP'den tahmin KASTEN yapılmıyor: VPN, kurumsal
             * ağ ve mobil operatör o tahmini düzenli olarak yanlışlar.
             */
            'region' => [
                'weight' => 20,
                'resolver' => RegionResolver::class,
                'options' => [
                    'cookie' => 'zbn_timezone',
                    'hints' => [
                        'Europe/Istanbul' => 'tr',
                        'Asia/Tehran' => 'fa',
                        'Europe/Moscow' => 'ru',
                        'Europe/Berlin' => 'de',
                        'Europe/Vienna' => 'de',
                        'Europe/Paris' => 'fr',
                        'Europe/Rome' => 'it',
                    ],
                ],
            ],

            /*
             * 30 · KAYNAK DİL. Zincirin son halkası; her zaman bir cevap
             * üretir, çünkü zincirin sonunda dilsiz kalmak bir seçenek değil.
             */
            'source' => [
                'weight' => 30,
                'resolver' => SourceLanguageResolver::class,
            ],

        ],

        /*
         * Dil TÜRÜ başına ayrı zincir — `docs/120` §4.1.
         *
         * Bir kullanıcının arayüzü İngilizce, okuduğu sayfa Türkçe olabilir
         * ve bu bir hata değildir. Ayrım bugüne kadar KAZAYLA doğruydu;
         * burada yazılı hâle geliyor.
         */
        'chains' => [
            'interface' => ['explicit', 'session', 'browser', 'region', 'source'],
            'content' => ['path', 'source'],
            'url' => ['path', 'source'],
        ],

        /*
         * SUNULAN DİLLERLE SÜZÜLEN TÜRLER.
         *
         * Yalnız arayüz. İçerik zinciri süzülseydi `/tr/` altındaki Türkçe
         * bir sayfa `lang="en"` ilan ederdi: ekran okuyucu yanlış telaffuz
         * eder, arama motoru yanlış dilde indeksler.
         */
        'shipped_only' => ['interface'],

    ],

    /*
    |--------------------------------------------------------------------------
    | Sahte-yerelleştirme (ölçüm dili)
    |--------------------------------------------------------------------------
    |
    | `docs/121` §4. Katalogdaki her metin gerçek bir dile çevrilmeden
    | mekanik olarak dönüşür: "Save changes" → "⟦Şåvê çhàñgêš ····⟧".
    |
    | BU BİR ÇEVİRİ DEĞİLDİR. Hiçbir dile ait değil, hiçbir çevirmen
    | çalışmadı, çeviri kilidi açılmadı, `shipped_locales` genişlemedi.
    | Yalnız bir ölçüm kipidir ve üç şeyi aynı anda görünür kılar: katalogdan
    | geçmeyen gömülü metin, uzayan metnin kırdığı düzen, ve ortasından
    | kesilen cümle.
    |
    | Varsayılan KAPALI ve `PseudoLocalizer::isEnabled()` üretimde bu ayara
    | hiç bakmaz — bir ortam değişkeninin yanlışlıkla üretime taşınması
    | gerçek bir olaydır ve müşteri ekranında `⟦Şåvê⟧` görmek, ürünün
    | bozulduğu anlamına gelir.
    |
    */

    'pseudo_localization' => (bool) env('I18N_PSEUDO_LOCALIZATION', false),

];
