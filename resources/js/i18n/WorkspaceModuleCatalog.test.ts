import { describe, it, expect } from 'vitest';
import { readdirSync, readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { t } from './workspace';

/**
 * RED - S1 workspace i18n modular catalog contract.
 *
 * resources/js/i18n/workspace.ts (legacy flat literal catalog) is being
 * replaced by an aggregator of the same path that automatically discovers
 * module catalogs under resources/js/i18n/workspace/ via import.meta.glob,
 * merges them, and preserves all workspace.* key/value pairs (media.ts
 * intentionally replaces one legacy status key with eight status-specific
 * keys) and t() runtime/type behavior exactly.
 */

const WORKSPACE_DIR = path.dirname(fileURLToPath(import.meta.url));
const CATALOG_DIR = path.join(WORKSPACE_DIR, 'workspace');
const AGGREGATOR_FILE = path.join(WORKSPACE_DIR, 'workspace.ts');

const FROZEN_MODULE_FILENAMES = [
    'analytics.ts',
    'billing.ts',
    'brand-locations.ts',
    'dashboard.ts',
    'media.ts',
    'menu.ts',
    'ordering.ts',
    'profile.ts',
    'publication.ts',
    'ratings.ts',
    'shell.ts',
    // FF-201: ON ÜÇÜNCÜ modül dosyası — destek ekranı (`docs/125`).
    'support.ts',
    'team.ts',
];

/*
    1705 → 1736 (FF-226, `docs/107` Faz 3.3, `docs/138`): veri hakları
    bölümü — Ayarlar > Çalışma alanı'nın tehlikeli bölgesi — otuz bir
    kaynak dizesi getirdi.

    OTUZ BİR, otuz üç değil: ilk yazımda kullanılmayan iki anahtar vardı
    ve `DS-BUNDLE-BUDGET-07` onları ölçtü. Masaüstü kapanışı 199.24 KB'den
    200.02 KB'ye çıkıp 200 KB bütçesini aşınca, bölümün kendisi `lazy`
    yapıldı ve kullanılmayan anahtarlar silindi; kapanış 199.96 KB'ye indi.
    Kimsenin okumadığı bir dize de indirilir.

    Sayı ve SHA burada bilerek DONMUŞ duruyor: bir dizenin sessizce
    değişmesi, PO dosyalarındaki karşılığını kimseye söylemeden
    geçersizleştirir ve çevirmen aynı cümleyi ikinci kez çevirmek zorunda
    kalır (`docs/121`).
*/
const FROZEN_LEGACY_KEY_COUNT = 1736;

// FF-137: panel v3 — on ekran ve medya modülü yenilendi, Mutfak rolü doğdu.
// FF-138d: ekipten çıkarmanın iki ayrı reddi (sahip değilsin / o üyelik yok)
// kendi cümlelerini kazandı; tek bir "tekrar deneyin" ikisini de yanlış
// anlatıyordu.
// FF-148: şube kartı "şu an açık / şu an kapalı" diyebiliyor. İki anahtar,
// çünkü rozet renkle değil KELİMEYLE anlatır; üçüncü bir "bilinmiyor"
// anahtarı YOK — cevabı olmayan şubede rozet hiç çizilmez.
// FF-150: yükleme ekranı, karantinada BEKLEYEN dosyayı artık sessizce
// "tamamlandı" diye göstermiyor. İki anahtar: biri dosyanın ulaştığını ama
// kullanılamadığını söyler, diğeri bunun sahibin hatası olmadığını. Süre,
// yüzde ya da "yakında düzelecek" diyen üçüncü bir anahtar YOK — bilinen
// tek şey durumun kendisidir.
// FF-151: aynı dürüstlük bir adım GERİYE taşındı — "her görsel taranır"
// vaadi, sahip henüz hiçbir şey yüklemeden okunuyor. Tarayıcı bağlı değilken
// onun yerine ortamın gerçeğini söyleyen TEK anahtar eklendi; ikinci cümle
// (bunun sahip hatası olmadığı) FF-150'nin anahtarını yeniden kullanır,
// çünkü sahip aynı gerçeği iki farklı sesle okumamalı.
// FF-155: "aranan ama bulunamayan" cümlesi ölçümün söylediğini söylüyor.
// Uç ham vuruşu değil FARKLI ZİYARETÇİYİ sayıyor; aynı ekranın listesi
// zaten "{count} ziyaretçi" diyordu, üstteki özet ise "{count} kez arandı".
// Aynı ölçümün iki cümlesinden biri yalandı. Anahtar sayısı DEĞİŞMEDİ —
// yalnız tek bir anahtarın metni düzeltildi.
// FF-157: zamanlanmış yayın ÇIKMADIĞINDA ekran artık susmuyor. Altı anahtar:
// dört durum cümlesi (yayına alınıyor / vakti geçti ve çıkmadı / başladı
// bitmedi / denendi kaydedilemedi), okunamayan bir durum için "menünün
// değişip değişmediğini söyleyemem" cümlesi, ve uyarıyı kapatan düğme.
// Dördü de aynı üç şeyi söyler ve hiçbiri SÖZ VERMEZ: ne oldu, menünün şu
// anki hâli ne, sahip ne yapabilir. "Yakında yayınlanacak" ya da tahmini
// süre diyen bir anahtar YOK — zamanlayıcının ne zaman döneceğini bilmiyoruz.
// FF-158: yükleme sınırı artık TÜRE göre. Dört yeni anahtar — üçü türün
// sahibin kelimesiyle adı (görseller / SVG dosyaları / PDF dosyaları),
// dördüncüsü seçilen yerin boyut sınırını dosya gönderilmeden ÖNCE yazan
// satır. Ayrıca `upload.error.tooLarge` METNİ değişti: artık hangi türün
// sınırına takıldığını da söylüyor, çünkü "sınır 25 MB" cümlesi 3 MB'lık
// bir SVG'yi geri çeviren bir kapıda doğrudan yanlıştı. VİDEO için anahtar
// YOK — kabul edilmeyen bir tür için sınır cümlesi yazmak, olmayan bir
// yeteneği ilan etmek olurdu (`docs/109` §8.2).
// FF-160: bekleyen davetin E-POSTASI çıktı mı? Sekiz anahtar — ikisi satırın
// teslimat hâli (çıkmadı / hiç gönderilip gönderilmediğini bilmiyoruz),
// altısı yeniden gönderme yolu (düğme, gönderiliyor, sağlayıcı kabul etti,
// yenilendi ama çıkmadı, gönderilemedi, ve bağlantının değiştiği notu).
// "Teslim edildi" ya da "gelen kutusuna düştü" diyen bir anahtar YOK:
// sağlayıcının mesajı devraldığını görürüz, gelen kutusunu göremeyiz.
// "Gönderildi" rozeti de YOK — yolunda giden satıra rozet basmak, dikkat
// isteyen iki hâli gürültünün içinde kaybederdi.
// FF-161: zamanlanmış yayının cümleleri artık "İstanbul" demiyor. Anahtar
// sayısı DEĞİŞMEDİ — yalnız iki anahtarın METNİ düzeldi (yardım satırı ve
// kurulu planın durum cümlesi). Saat dilimi markanın değil ŞUBENİN alanıdır
// (`docs/62`): aynı markanın Berlin şubesinde "Saatler İstanbul saatidir"
// cümlesi, doğru hesaplanmış bir anı yanlış şehirle okutuyordu. Şehir adı
// yerine ŞUBE denir, çünkü ekrandaki saati belirleyen şubenin kendisidir.
// FF-163: menü denetim izi artık okunabilir bir ekranı var. Otuz iki anahtar
// — on dört eylem cümlesi (menü/kategori/ürün yaşam döngüsü, fiyat,
// görünürlük, alerjen), CSV aktarımından AYRI duran "fotoğraftan okundu,
// onaylandı", tanınmayan bir kod için tek bir dürüst cümle, öncesi/sonrası
// biçimi, görünürlüğün iki kelimesi, bölümün durum cümleleri ve sayfalama.
// İki anahtar listenin geri kalanından daha önemli: `empty` ("henüz bir
// değişiklik kaydedilmedi") ve `notRecorded` — sıralama, "bugün tükendi" ve
// yayınlama bilerek ize yazılmıyor ve bunu SÖYLEMEYEN bir liste, olmayan bir
// kaydı "olmadı" diye okutur. Tahmini süre, sayaç ya da "yakında" diyen bir
// anahtar YOK; kayıt yoksa yazılan tek şey `empty`dir.
// FF-165: Insights sayaçları kanonik kaynağa çekildi. NET +3 anahtar (1378 →
// 1381): dört yeni (`metric.menuOpen.support`, `metric.uniqueVisitors.support`,
// `menuEngineering.neverViewed.review`, `.reviewFor`) eksi bir kaldırılan
// (`metric.openRate`). Açılış oranı beşinci bir sayaç kartıydı; oysa oran iki
// sayının BİLEŞİMİ — kaynağın ızgarasında dört kart var ve oran, açıkladığı
// sayının alt satırında duruyor. `metric.qrResolve` ile `metric.menuOpen`
// METNİ de düzeldi: "QR Resolve"/"Confirmed Menu Open" `docs/12`'nin ölçüm
// terimleriydi ve ayrım korunuyor, ama sahibi ekranda kaynağın kelimelerini
// okuyor ("Tarama", "Menü açılışı"). "Hiç bakılmayan" satırının düğmesi
// kaynakta "Gizle" yazar; bu ekran gizlemez, gizlenecek yere götürür ve
// etiketi de bunu söyler — bastığı düğmenin adı yalan olan bir liste,
// ölçüme duyulan güveni tek tıkta harcar.
// Aynı pakette dört başlığın METNİ de kaynağa çekildi (anahtar sayısı
// değişmedi): ısı haritası artık "En yoğun saatler" değil "Saatlere göre
// yoğunluk" — ızgara haftanın her saatini çiziyor, bir "ilk beş" değil;
// masa listesi başlığı sınırını söylüyor ("ilk 5"), çünkü sınırı yazmayan
// bir başlık listenin sonunu menünün sonu sandırır; ve sürüm dipnotu
// kaynağın kendi cümlesi oldu ("geri alma da bir yayındır"), sahibin geri
// alma anındaki iki korkusunu —kayıt gider mi, kartlar ölür mü— tek cümlede
// karşılamak için.
// FF-164: QR ekranı panel v3.1 kanonik kaynağına göre yenilendi ve bir kod
// listesinden BASKI SİPARİŞİNE döndü. +63 anahtar (1381 → 1444): kaynağın üç
// adımı ve dört hazır çıktısı, kâğıt/oran/yön/biçim seçicileri, üç kapsam
// (hepsi / bir bölge / tek masa) ve notları, beş tasarımın adı, önizlemenin
// ölçü etiketi ve alt çubuğun tek cümlelik özeti.
//
// ÜÇ ŞEY BİLEREK ANAHTARSIZ KALDI, çünkü depoda karşılıkları yok ve olmayan
// bir yeteneği ilan eden bir metin, kullanıcıya ürünü yanlış tanıtır:
// kaynağın "Temaları yönet" düğmesi (Ayarlar'da kalıcı baskı teması diye bir
// kayıt yok), PNG biçimi (kart bestecisi yalnız SVG/PDF üretir — bunun sebebi
// `custom.noPng` ile YAZILIYOR) ve kesim payı / alt satır / logo-masa-adres
// anahtarları (bestecide karşılıkları yok).
//
// `qrScreen.noDarkTheme` anahtarının METNİ de değişti — sayı değişmedi. Eski
// cümle "koyu kart yoktur" diyordu ve o gün doğruydu; kaynağın koyu tasarımı
// kodun kendisini ters çeviriyordu. Panel v3.1 o kusuru düzeltti (koyulaşan
// şey kartın zemini, kod hâlâ koyu modül / açık zemin), tasarım doğdu ve
// cümle yalan olacaktı. Yerine kısıtın kendisi yazıldı; iki yüzeyde de doğru.
//
// FF-170: SAYI İLK KEZ AŞAĞI İNDİ — 1444'ten 1430'a, on dört anahtar.
// `QrSelectedCodePanel` panel v3.1 QR ekranı yeniden yazıldığında çağıransız
// kaldı ve silindi; yalnız onun okuduğu anahtarlar (seçili kod başlığı, üç
// durum rozeti, adres kopyalama çifti, tasarım/ölçü kontrolleri,
// `downloadPdf`, kontrast satırı ve `noDarkTheme`) onunla birlikte düştü.
// Yeni ekranın kendi sözlüğü var (`cardTheme.*`, `preset.*`, `custom.*`), yani
// bunlar eş anlamlı değil devrilmiş anahtarlardı. Bu sayacın büyümek zorunda
// olmadığı burada görünür oluyor: okuyucusu kalmayan bir dizeyi altı dilde
// çevirtmeye devam etmek, kimsenin görmediği bir cümleyi bakımda tutmaktır.
// FF-174: 1430 → 1445, on beş anahtar. Marka görünümü ekranının sözlüğü:
// bir ton alanı, altı biçim seçeneği ve iki dürüstlük cümlesi — biri
// "rengini okunması için biraz koyulaştırdık", diğeri "bu görünüm Restaurant
// planıyla gelir, menün yine yayınlanır". İkincisi bu depodaki bir kuralın
// karşılığıdır: yapılamayan iş sessizce yok sayılmaz, adıyla söylenir.
// FF-179: 1445 → 1532, seksen yedi anahtar ve ONBİRİNCİ modül dosyası
// (`ordering.ts`). Sipariş akışının panel yüzeyi: garson kuyruğu, mutfak
// monitörü, sipariş şalteri ve geçmiş (`docs/115` S4/S5/S6).
//
// Sayının bu kadar büyümesi, bu ekranların ÜÇ AYRI İNSANA konuşmasından:
// garsona (onayla/reddet ve ret sebebi), aşçıya (tam ekran, ilerlet,
// alerjen) ve sahibe (şalter, geçmiş). Üçünün de kendi boş-durum,
// hata ve kısıt cümleleri var, çünkü çıkış yolları farklı — "sipariş yok"
// ile "sipariş alma kapalı" tek anahtara indirilseydi, kapalı bir hizmet
// sessiz bir akşam gibi görünürdü (`docs/115` Y1).
// FF-187: 1532 → 1533, TEK anahtar (`qrScreen.downloadShort`). Karekod
// eylem çubuğundaki indirme düğmesi artık kodun adını tekrar etmiyor —
// ad zaten hemen üstündeki özet cümlesinde. Uzun etiketli anahtar
// SİLİNMEDİ: adın gerçekten gerektiği bağlamsız bir yer (liste, e-posta)
// çıkarsa hazır duruyor.
// FF-184 yeniden tabanlama: 1533 → 1541, sipariş planı kapısının sekiz
// anahtarı. Sayı ve SHA main ile birleştikten SONRA yeniden hesaplandı;
// çakışma iki dalın aynı donmuş sayıyı ayrı ayrı büyütmesindendi.
// FF-19x (docs/122 Y4): 1541 → 1576, ON İKİNCİ modül dosyası (`ratings.ts`).
// Panelde puanlama ekranı: misafirin verdiği oy artık sahibin gözünün
// önünde — eşikli özet, ürün başına dağılım, sahibin yanıtı ve boş durum.
// main ile birleştikten SONRA yeniden hesaplandı (ratings.ts + billing.ts).
// FF-197: 1576 → 1618, kırk iki anahtar — kendi kendine abonelik
// (docs/107 Faz 1.1 + 1.3, docs/123). Otuz biri ödeme panelinin cümleleri
// (kip uyarısı, plan seçimi, fatura profili durumu, ödeme durumu, son
// ödemenin hâli), on biri fatura profili formunun alan adları ve düğmeleri.
//
// İKİ ŞEY BİLEREK ANAHTARSIZ: kart numarası/son kullanma/CVV etiketleri
// (kart yalnız Iyzico'nun sayfasına girilir; bu panelde o alan YOKTUR ve
// bir etiket yazmak olmayan bir alanı ilan etmek olurdu) ve "ödeme alındı,
// fatura kesildi" cümlesi (fatura yolu bu pakette yok — docs/107 Faz 1.4).
// "Test mode" cümlesi sandbox kipinde okunur: prova gerçek yolun aynısıdır
// ve sahip hangi kipte olduğunu tahmin etmek zorunda kalmaz.
// FF-216: 1618 → 1629, on bir anahtar — ödeme adımının İKİ ONAYI ve
// satıcının eksik kimliği (docs/107 Faz 1.2, docs/131). İkisi onay kutusunun
// cümlesi, ikisi kutu boşken yazılan sebep, biri bölümün başlığı, biri belge
// listesinin adı, dördü okunacak belgelerin bağlantı etiketi (ön
// bilgilendirme, mesafeli satış, teslimat/ifa, iptal-iade), biri de canlı
// kipte satıcının yasal kimliği yayınlanmadan tahsilat yapılamadığını
// söyleyen cümle.
//
// İKİ AYRI ONAY ANAHTARI, çünkü iki ayrı hukuki olgu: sözleşmeyi kabul etmek
// ile cayma süresi dolmadan ifaya başlanmasını AÇIKÇA istemek aynı şey
// değildir. Tek bir "kabul ediyorum" anahtarı, defterde hangisine evet
// dendiğini ayırt edilemez hâle getirirdi.
//
// BİLEREK ANAHTARSIZ: bir kart ya da banka markası (hangi kartların kabul
// edildiği ödeme sağlayıcısının yapılandırmasından türer, bu depoda öyle bir
// liste yok) ve bir "cayma süresi şu kadar gün" cümlesi (süre kanundan gelir
// ve belge metninde, katalogda değil).
// main ile birleştikten SONRA yeniden hesaplandı (ratings.ts + support.ts).
// FF-201: 1576 → 1599, yirmi üç anahtar ve ON ÜÇÜNCÜ modül dosyası
// (`support.ts`). Destek ekranı (`docs/125`): başlık ve açıklama, yardım
// bağlantısı (2), yeni talep formu (etiketler, yardım cümleleri, düğme ve
// ÜÇ sonuç cümlesi — alındı e-postası çıktı / çıkmadı / istek düştü),
// liste (başlık, üç durum cümlesi, iki tarih satırı) ve üç durum kelimesi.
//
// YANIT TAAHHÜDÜ İÇİN ANAHTAR YOK ve olmayacak: cümle sunucudan gelir
// (`site.support.commitment`), çünkü iletişim sayfası, alındı e-postası ve
// panel tek kaynağı okumak zorunda. Buraya ikinci bir cümle yazmak, iki
// cümlenin ayrıştığı günü hazırlamak olurdu. "7/24", "en kısa sürede" gibi
// bir yedek cümle de yok — vaat sahibin kararıdır, katalogun değil.
// FF-215: 1618 → 1634, on altı anahtar — FATURA (docs/107 Faz 1.4,
// docs/130). Yukarıdaki "fatura yolu bu pakette yok" notu ARTIK GEÇERSİZ:
// tahsilatın karşılığında numaralı bir belge doğuyor ve panel onu
// listeliyor. Anahtarlar belgenin EKRAN kipinindir; kâğıt kipi (A4 PDF)
// sunucuda üretilir ve metni katalogda değil, belgenin kendi kaynak
// dilindedir (`LegalDocument` ile aynı karar).
//
// ÜÇ CÜMLE BİLEREK "EKSİK" DİYOR ve hiçbiri iyimser değil: şirket bilgisi
// girilmemişken belgenin tam bir ticari fatura OLMADIĞI, KDV oranı
// yapılandırılmamışken vergi ayrımının GÖSTERİLMEDİĞİ, ve e-arşiv/
// e-fatura sağlayıcısı bağlı olmadığı için kaydın hiçbir yere
// GÖNDERİLMEDİĞİ. "Yakında e-fatura" diye bir anahtar yok ve olmayacak.
// FF-213 (docs/121 Ö1/Ö2): 1576 → 1579, ÜÇ anahtar ve hiçbiri yeni bir
// ekran değil. Üçü de ZATEN EKRANDA olan ama katalogdan geçmeyen metinler:
// kırıntı izinin bölge adı ve boş hâli (`Breadcrumbs` içinde gömülüydü) ve
// çalışma alanı seçicisinin erişilebilir adı — o ad arayüzde
// birleştiriliyordu, artık ADLI yer tutuculu tek bir anahtar.
// Sayının büyümesi burada bir yüzey büyümesi değil, GÖRÜNÜRLÜK: sahte-
// yerelleştirme bu üç metnin dönüşmediğini gösterdi (`docs/121` §4).
// FF-226: veri hakları bölümü — arşiv almanın ve silme istemenin ekrandaki
// cümleleri. "Her şey silinir" DEMEYEN bir metin bilerek yazıldı: saklanan
// tabloların listesi sunucudan gelir ve ekranda adıyla sayılır.
// ÇEVİRİ YAPILMADI: yalnız İngilizce kaynak satırı yazıldı, öteki dillerin
// msgstr'leri boş ve `shipped_locales` hâlâ ['en'].
// FF-219: 1660 → 1694, otuz dört anahtar — ABONELİĞİN EKSİK YARISI
// (docs/107 Faz 1.3, docs/134). İptal, iptalden cayma, plan düşürme,
// ödemesiz süre ve askı. Bu grubun ayırt edici yanı, cümlelerin bir DURUM
// değil bir TARİH ve bir SONUÇ söylemesidir: "aboneliğiniz güncellendi"
// diye bir anahtar yok; "X tarihine kadar kullanmaya devam edeceksiniz",
// "X tarihinde şunları kaybedeceksiniz", "X tarihinden beri kapalı" var.
// İki cümle bilerek MİSAFİRİ anlatıyor — sahibin en çok korktuğu şey,
// ödeme sorununun masadaki müşterisine yansımasıdır ve cevabı ("yansımaz")
// ekranda yazılı.
// ÇEVİRİ YAPILMADI: yalnız İngilizce kaynak satırı yazıldı, öteki dillerin
// msgstr'leri boş ve `shipped_locales` hâlâ ['en'].
const FROZEN_LEGACY_NORMALIZED_SHA256 =
    '8d0905a13625691165262ae8307d7bdfe46fb07d87a689ba9ba73a0b60e907ed';

function normalizedHash(entries: Record<string, string>): string {
    const sortedKeys = Object.keys(entries).sort();
    const normalized = sortedKeys.map((key) => `${key}=${entries[key]}`).join('\n');
    return createHash('sha256').update(normalized, 'utf8').digest('hex');
}

describe('workspace i18n modular catalog contract', () => {
    // Başlıktaki SAYI kaldırıldı ve bir daha yazılmayacak: liste zaten
    // aşağıda duruyor ve iki yerde tutulan bir sayı, ikincisi güncellenmediği
    // gün testin adını yalancı yapar (bir süre "dokuz" yazarken on dosya
    // sayıyordu).
    it('discovers exactly the frozen module catalog filenames under resources/js/i18n/workspace/', () => {
        const actualFilenames = readdirSync(CATALOG_DIR)
            .filter((name) => name.endsWith('.ts'))
            .sort();

        expect(actualFilenames).toEqual(FROZEN_MODULE_FILENAMES);
    });

    it('aggregates via automatic eager glob discovery with no static per-module import list and no inline translation literals', () => {
        const aggregatorSource = readFileSync(AGGREGATOR_FILE, 'utf8');

        expect(aggregatorSource).toMatch(/import\.meta\.glob/);

        for (const filename of FROZEN_MODULE_FILENAMES) {
            const moduleName = filename.replace(/\.ts$/, '');
            const staticImportPattern = new RegExp(
                `from\\s+["'\`]\\./workspace/${moduleName}["'\`]`,
            );
            expect(aggregatorSource).not.toMatch(staticImportPattern);
        }

        expect(aggregatorSource).not.toMatch(/['"]workspace\.[a-zA-Z]+['"]\s*:/);
    });

    it('rejects a duplicate key across catalogs with a deterministic error naming the key and source module', () => {
        const aggregatorSource = readFileSync(AGGREGATOR_FILE, 'utf8');

        expect(aggregatorSource).toMatch(/throw/);
        expect(aggregatorSource).toMatch(/duplicate/i);
    });

    it('composes exactly the frozen number of workspace translation entries matching the frozen normalized legacy SHA-256', async () => {
        const workspaceModule: typeof import('./workspace') = await import('./workspace');
        const composed = workspaceModule.workspaceTranslations as Record<string, string>;

        expect(composed).toBeTruthy();
        expect(Object.keys(composed)).toHaveLength(FROZEN_LEGACY_KEY_COUNT);
        expect(normalizedHash(composed)).toBe(FROZEN_LEGACY_NORMALIZED_SHA256);
    });

    it('t() preserves representative values, supports {var} interpolation, rejects an unknown literal at compile time, and falls back to it at runtime', () => {
        expect(t('workspace.loading')).toBe('Loading your workspace…');
        expect(t('workspace.billing.currentPlan.version')).toBe('Version {version}');
        expect(t('workspace.billing.currentPlan.version', { version: '7' })).toBe('Version 7');
        expect(
            t('workspace.billing.iyzicoSandbox.amount', { amount: '120', currency: 'TRY' }),
        ).toBe('Amount 120 TRY');

        function assertRejectsUnknownLiteral(): void {
            // @ts-expect-error only literal WorkspaceTranslationKey values are accepted by t()
            t('workspace.__unknown_literal_not_in_catalog__');
        }
        void assertRejectsUnknownLiteral;

        const untypedT = t as (key: string, vars?: Record<string, string>) => string;
        const unknownKey = 'workspace.__unknown_literal_not_in_catalog__';
        expect(untypedT(unknownKey)).toBe(unknownKey);
    });
});
