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
 * ── Yalnız kaynak dil ────────────────────────────────────────────────────
 *
 * Kütükteki 386 Türkçe satırın metni YOK ve çeviri kilidi kapalı
 * (`docs/120` §7). Bu dosyaya bir Türkçe satır girmez; girseydi ölçüm onu
 * zaten reddederdi.
 *
 * @var list<array{page_key: string, locale: string, decided_by: string, decided_on: string, reason: string}>
 */

/*
    Karar sahibi ve gün, on sekiz satırın hepsinde aynı — çünkü karar tek bir
    oturumda, tek bir kişi tarafından verildi. Yine de her satırda TEKRAR
    yazılıyor: bir sonraki sayfa başka bir gün, başka bir sebeple açılacak ve
    o gün "dosyanın başındaki karar" diye bir şey olmamalı.
*/
$owner = 'Zabuno sahibi';
$day = '2026-09-08';

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
];
