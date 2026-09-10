<?php

declare(strict_types=1);

/**
 * YAYIN KARARLARI — bir insanın verdiği, adıyla sayılmış kararlar (`docs/144`).
 *
 * ── Bu dosya neden var ───────────────────────────────────────────────────
 *
 * `site:sync-content-status` bir sayfayı en çok `content_draft`a taşır ve bu
 * tavan tartışmaya kapalıdır: *"Bir betiğin atlayabildiği kapı, kapı
 * değildir."* O karar bu dosyayla BOZULMUYOR — tavan yerinde duruyor ve o
 * komut hâlâ ölçümden türeyen tek kademeyi yürütüyor.
 *
 * Burada olan şey başka: kalite kapısından bir İNSAN geçmiş, kararını
 * vermiş, ve karar uygulanacak. Aradaki fark bir üslup farkı değil, dosyanın
 * ŞEKLİDİR:
 *
 *   - Toptan bir "her şeyi yayınla" kuralı YOK. Her sayfa ADIYLA sayılır.
 *   - Her satır kararı VERENİ, VERİLDİĞİ GÜNÜ ve SEBEBİ taşır.
 *   - Dosya kod incelemesinden geçer; bir sayfayı yayına almak bir taahhüt
 *     (commit) gerektirir, bir bayrak değil.
 *
 * Kararın kaydı ikiye BÖLÜNMÜŞ değil, PAYLAŞTIRILMIŞTIR ve her olgunun tek
 * bir kopyası vardır: KİM ve NEDEN burada (ve Git geçmişinde) yaşar, NE ZAMAN
 * UYGULANDI ise kütükteki `published_at` damgasında. İkisinin ikinci bir
 * kopyasını bir tabloya yazmak, bir gün ayrışacak iki kayıt üretirdi.
 *
 * ── Yayınlanabilirlik iddia edilmez, ÖLÇÜLÜR ──────────────────────────────
 *
 * Buraya bir anahtar yazmak yetmez. `site:apply-publication-decisions` her
 * satır için iki şeyi ölçer ve ölçemezse YÜKSEK SESLE durur:
 *
 *   1. O sayfanın O DİLDE yazılmış bir metni var mı (`ProductPageLibrary`)?
 *   2. O satır kütükte var mı (`content_pages`)?
 *
 * Metni olmayan bir sayfa "yayında" işaretlense bile ziyaretçiye 404 döner
 * (`ResolvePageDelivery` son emniyet kemeri). Yayınlanmış GÖRÜNÜP 404 dönen
 * bir sayfa, hiç yayınlanmamış olandan kötüdür: arama motoru onu bulur,
 * ziyaretçi tıklar, ve ikisi de boş bir odaya girer.
 *
 * ── İki dil, ADIYLA SAYILMIŞ ──────────────────────────────────────────────
 *
 * Bu dosya bir dönem yalnız kaynak dili tanıyordu ve gerekçesi ölçülebilirdi:
 * kütükteki 386 Türkçe satırın METNİ YOKTU. Ölçüm hâlâ aynı yerde duruyor ve
 * hâlâ aynı işi yapıyor — değişen şey, on sekiz sayfanın Türkçe metninin
 * artık YAZILMIŞ olması.
 *
 * Sahibin açık kararı (2026-09-10): *ana dil İngilizce eksiksiz bitecek,
 * Türkçe ikinci dil olacak ve çevirilerin eksiği kalmayacak.* Aşağıdaki
 * Türkçe satırlar o kararın uygulanmış hâlidir ve toptan bir "Türkçeyi aç"
 * kuralı DEĞİLDİR: on sekizi de İngilizce kardeşi gibi adıyla sayılır, adıyla
 * gerekçelendirilir ve kod incelemesinden geçer.
 *
 * Geri kalan 368 Türkçe satır bu dosyaya GİRMEZ; metni olmayan bir satırı
 * yayına almak, `site:apply-publication-decisions` tarafından zaten
 * reddedilirdi.
 *
 * @var list<array{page_key: string, locale: string, decided_by: string, decided_on: string, reason: string}>
 */

/*
    Karar sahibi ve gün, bir dilin on sekiz satırının hepsinde aynı — çünkü o
    karar tek bir oturumda, tek bir kişi tarafından verildi. Yine de her
    satırda TEKRAR yazılıyor: bir sonraki sayfa başka bir gün, başka bir
    sebeple açılacak ve o gün "dosyanın başındaki karar" diye bir şey
    olmamalı.

    İKİ GÜN VAR ve ikisi ayrı kararlardır. İngilizce sayfalar 2026-09-08'de
    açıldı; Türkçe karşılıkları 2026-09-10'da, sahibin ikinci dil kararının
    ardından. Türkçe satırlara İngilizce günü yazmak, verilmemiş bir kararı
    geçmişe taşımak olurdu.
*/
$owner = 'Zabuno sahibi';
$day = '2026-09-08';
$turkishDay = '2026-09-10';

return [
    /*
        ── Ürün ağacının kökü ───────────────────────────────────────────────

        Kök olmadan alt sayfalar bir yere bağlanmaz: altbilginin grup iskeleti
        sayfaların KENDİ hiyerarşisinden (`parent_key`) çıkıyor ve atası
        bağlanamayan sayfalar tek bir "Explore" yığınına düşüyor.
    */
    [
        'page_key' => 'urun',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Ürün ağacının kökü; alt sayfaların altbilgide bir başlık altında toplanması buna bağlı.',
    ],

    /*
        ── Ürünün kendisi ───────────────────────────────────────────────────

        Onu satın alacak kişinin sorduğu sorular. Her birinin İngilizce metni
        depoda yazılı (FF-191/192/203/229) ve bugüne kadar hiçbir yerden
        açılamıyordu.
    */
    [
        'page_key' => 'urun.qr-menu',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Ürünün ana vaadi; arama niyetinin en yoğun olduğu sayfa ve metni yazılı.',
    ],
    [
        'page_key' => 'urun.menu-yonetimi',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Menü yönetimi hub sayfası; dört alt sayfasının ebeveyni ve altbilgide kendi grubunu doğuruyor.',
    ],
    [
        'page_key' => 'urun.masa-ve-qr-yonetimi',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Masa ve karekod yönetimi satılan bir yetenek ve sitede tek satırı yoktu.',
    ],
    [
        'page_key' => 'urun.analitik',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Analitik, plan karşılaştırmasında adı geçen bir yetenek; anlatıldığı sayfa açılıyor.',
    ],
    [
        'page_key' => 'urun.zabuno-ai',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Zabuno AI ürünün ayırt edici tarafı; yalnız fiyat sayfasında anılması yetersizdi.',
    ],
    [
        'page_key' => 'urun.gorsel-ve-medya',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Görsel ve medya yönetimi, menü kurulumunun ilk günkü en somut sorusu.',
    ],
    [
        'page_key' => 'urun.coklu-dil-ve-para-birimi',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Turistik işletmenin ilk sorduğu şey; metni yazılı ve bir yerden açılması gerekiyor.',
    ],
    [
        'page_key' => 'urun.coklu-sube',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Zincir işletme sorusu; satın alma kararını doğrudan etkileyen bir sınır anlatılıyor.',
    ],
    [
        'page_key' => 'urun.tasarim-ve-marka',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Markasının nasıl görüneceğini soran işletmenin sayfası; metni yazılı.',
    ],
    [
        'page_key' => 'urun.siparis',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Masadan sipariş PARASI ALINAN bir yetenekti ve sitede anlatıldığı tek bir sayfa yoktu (docs/137 §5).',
    ],

    /*
        ── Menü yönetiminin alt sayfaları ───────────────────────────────────

        Üç kademeli adresler. Ebeveyni yayında olduğu için altbilgide kendi
        grubunu kuruyorlar; ebeveyn kapalı kalsaydı hepsi "Explore" yığınına
        düşerdi.
    */
    [
        'page_key' => 'urun.menu-yonetimi.kategoriler',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Menü kurulumunun ilk adımı; hub sayfasının içini dolduran dört sayfadan biri.',
    ],
    [
        'page_key' => 'urun.menu-yonetimi.urunler',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Ürün/yemek kaydının nasıl yapıldığı; hub sayfasının içini dolduran dört sayfadan biri.',
    ],
    [
        'page_key' => 'urun.menu-yonetimi.urun-fiyatlari',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Fiyat değişikliğinin nasıl yürüdüğü; basılı menünün çözemediği sorunun tam karşılığı.',
    ],
    [
        'page_key' => 'urun.menu-yonetimi.stok-durumu',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Tükendi bilgisi ürünün en sık kullanılan günlük eylemi; metni yazılı.',
    ],
    [
        'page_key' => 'urun.menu-yonetimi.menu-versiyonlari',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Yayın ve geri alma yalnız /help içinde bir paragraftı; kendi sayfası açılıyor (docs/137 §5).',
    ],

    /*
        ── Ürünün dışındaki iki giriş ───────────────────────────────────────

        `/en/pricing/` bugün yayında olan `/pricing` ile ÇAKIŞMAZ: kurumsal
        kapı yalnız dil dizini altında çalışır ve iki adres birbirinin
        kopyası değil — biri kurumsal kütüğün sayfası, öteki yaşayan rota.
        Adres göçü ayrı bir paketin işi (`docs/105` §4.1).
    */
    [
        'page_key' => 'cozumler',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Çözümler girişi; ürün ağacının yanındaki ikinci giriş kapısı ve metni yazılı.',
    ],
    [
        'page_key' => 'fiyatlandirma',
        'locale' => 'en',
        'decided_by' => $owner,
        'decided_on' => $day,
        'reason' => 'Kurumsal kütükteki fiyat sayfası; plan karşılaştırması ve SSS tek sayfada yazılı.',
    ],

    /*
        ── TÜRKÇE KARŞILIKLAR (2026-09-10) ──────────────────────────────────

        Sahibin ikinci dil kararının uygulanmış hâli. On sekiz İngilizce
        sayfanın on sekizinin de Türkçe metni YAZILDI; bu satırlar yalnız o
        metinlerin adresini açıyor.

        Sıra İngilizce listeyle birebir aynı ve bu bilinçli: iki listeyi yan
        yana okuyan biri eksik bir satırı tek bakışta görür. Eksik bir satır
        burada, dil değiştiricide "karşılığı yok" demek ve hreflang'de bir
        iddiadan vazgeçmek anlamına gelirdi.
    */
    [
        'page_key' => 'urun',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Türkçe ürün ağacının kökü; Türkçe altbilginin grup iskeleti de bu satıra bağlı.',
    ],
    [
        'page_key' => 'urun.qr-menu',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Ürünün ana vaadinin Türkçesi; Türkiye pazarında arama niyetinin en yoğun olduğu sayfa.',
    ],
    [
        'page_key' => 'urun.menu-yonetimi',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Menü yönetimi hub sayfasının Türkçesi; dört Türkçe alt sayfasının ebeveyni.',
    ],
    [
        'page_key' => 'urun.masa-ve-qr-yonetimi',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Masa ve karekod yönetimi satılan bir yetenek; Türkçe okuyan işletmecinin de sayfası olmalı.',
    ],
    [
        'page_key' => 'urun.analitik',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Analitik plan karşılaştırmasında adı geçiyor; Türkçe karşılığı olmadan Türkçe fiyat sayfası eksik kalırdı.',
    ],
    [
        'page_key' => 'urun.zabuno-ai',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Zabuno yapay zekâsı ürünün ayırt edici tarafı; ikinci dilde de anlatılmadan satılamaz.',
    ],
    [
        'page_key' => 'urun.gorsel-ve-medya',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Görsel ve medya, menü kurulumunun ilk günkü en somut sorusu; soruyu soran çoğunlukla Türkçe soruyor.',
    ],
    [
        'page_key' => 'urun.coklu-dil-ve-para-birimi',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Turistik işletmenin ilk sorduğu şey; sayfanın kendisi iki dilliyken tek dilde durması çelişkiydi.',
    ],
    [
        'page_key' => 'urun.coklu-sube',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Zincir işletme sorusu; merkezi yönetimin sınırı Türkçe okuyan sahibe de açıkça yazılmalı.',
    ],
    [
        'page_key' => 'urun.tasarim-ve-marka',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Markasının nasıl görüneceğini soran işletmenin sayfası; rengin nerede bittiği iki dilde de yazılı.',
    ],
    [
        'page_key' => 'urun.siparis',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Masadan sipariş PARASI ALINAN bir yetenek; Türkçe karşılığı olmadan Türkçe okuyan alıcı onu hiç görmezdi.',
    ],
    [
        'page_key' => 'urun.menu-yonetimi.kategoriler',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Menü kurulumunun ilk adımı; Türkçe hub sayfasının içini dolduran dört sayfadan biri.',
    ],
    [
        'page_key' => 'urun.menu-yonetimi.urunler',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Ürün kaydının nasıl yapıldığı; Türkçe hub sayfasının içini dolduran dört sayfadan biri.',
    ],
    [
        'page_key' => 'urun.menu-yonetimi.urun-fiyatlari',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Fiyat değişikliğinin nasıl yürüdüğü; basılı menünün çözemediği sorunun Türkçe karşılığı.',
    ],
    [
        'page_key' => 'urun.menu-yonetimi.stok-durumu',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Tükendi bilgisi ürünün en sık kullanılan günlük eylemi; mutfakta konuşulan dil Türkçe.',
    ],
    [
        'page_key' => 'urun.menu-yonetimi.menu-versiyonlari',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Yayın ve geri alma bir satın alma sorusudur; Türkçe okuyan işletmeci de cevabını sitede bulmalı.',
    ],
    [
        'page_key' => 'cozumler',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Çözümler girişi; sektöre özel sürüm olmadığı gerçeği iki dilde de aynı netlikte durmalı.',
    ],
    [
        'page_key' => 'fiyatlandirma',
        'locale' => 'tr',
        'decided_by' => $owner,
        'decided_on' => $turkishDay,
        'reason' => 'Kurumsal kütükteki Türkçe fiyat sayfası; tutarlar katalogdan okunur, Türkçe biçimlendirmeyle.',
    ],
];
