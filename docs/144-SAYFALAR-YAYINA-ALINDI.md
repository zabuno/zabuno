<!--
    KARAR BELGESİ — FF-236, 2026-09-08.

    Her "ölçüldü" ifadesi gerçekten koşturulmuş bir komuta dayanır.
    Ölçülemeyen şeyler §8'de AÇIKÇA "bilinmiyor" diye ayrılmıştır.
-->

# On sekiz sayfa yayına alındı

## 1. Sahibin gördüğü şey — ve haklı olduğu yer

Sahip zengin, çok gruplu bir altbilgi istedi. Altbilgi yazıldı, kütükten
türeyecek biçimde kuruldu, testleri geçti — ve **ekranda iki grup vardı.**
pSEO bandı hiç çizilmiyordu.

Sebebi tek cümleydi: **içeriği yazılmış on sekiz sayfanın hiçbiri yayınlanmış
değildi.** Altbilgi boş değildi, doldurulacak bir şey yoktu.

### Somut yolculuk — önce

Bir restoran sahibi "QR menü nasıl çalışır" diye arıyor. Zabuno'nun bu
sorunun cevabını anlatan bir sayfası **vardı**: metni yazılmıştı, ürünün ne
yaptığını ve ne yapmadığını satır satır anlatıyordu. Ama o sayfa hiçbir
adresten açılmıyordu, arama motoru onu hiç görmemişti, sitenin altbilgisinde
adı geçmiyordu. Yazılmış ama kimsenin okuyamadığı bir sayfa, yazılmamış bir
sayfadır.

### Somut yolculuk — şimdi

Aynı kişi `/en/product/qr-menu` adresini açıyor ve sayfayı okuyor. Sayfanın
altında ürünün öteki on yedi sayfasına giden bir menü var. `sitemap.xml` o
adresi arama motoruna ilan ediyor. Ve sitedeki her sayfanın altbilgisinden
oraya bir yol var.

## 2. Bozulmayan karar — bu belgenin en önemli bölümü

`SyncContentStatusCommand`'ın tavanı `content_draft`tır ve **bu pakette
yükseltilmedi.** Dosyanın kendi cümlesi de yerinde duruyor:

> Kalite kapısı insanların işidir. Bir betiğin atlayabildiği kapı, kapı
> değildir.

Burada yapılan şey o kapıyı atlamak değil, **kapıdan geçmiş bir insanın
kararını uygulamak.** Fark bir üslup farkı değil; dosyaların şekliyle
sabitlendi:

| Karar | Nerede yaşıyor | Neden böyle |
|---|---|---|
| Hangi sayfa açılacak | `config/content-publication-decisions.php` | On sekiz sayfa **adıyla** sayılıyor. Toptan "her şeyi yayınla" diye bir yol yok ve eklenemez. |
| Kim karar verdi, neden | Aynı dosyada, her satırda | Sebebi yazılmamış bir yayın, altı ay sonra kimsenin açıklayamadığı bir adrestir. |
| Ne zaman uygulandı | Kütükteki `published_at` | Aynı olgunun iki kaydı bir gün ayrışır; bu yüzden ikinci bir tablo AÇILMADI. |
| Karar değişirse | Git geçmişi | Bir sayfayı yayına almak bir taahhüt (commit) gerektirir, bir bayrak değil. |

### Kapı GEVŞEMEDİ, SIKILDI

Dağıtım sözleşmesindeki kapı (`DEPLOY-PAGE-LEDGER-12`) "giriş betiği kütüğü
doldurur, durumu ilerletmez" diyordu — ama ölçümü tek bir komut adını
arıyordu. İkinci bir komut yazıldığı için o yasak, hiç değiştirilmeden
yanından geçilebilir hâle gelmişti.

Bu pakette yasak yeni komutu da kapsayacak şekilde genişletildi ve
**ölçüme bağlandı**: `DEPLOY-PAGE-LEDGER-13`, kütüğün yayın durumuna yazan
her `site:` komutunu bulur ve listede olmasını şart koşar. Üçüncü bir komut
yazıldığı gün, kimse listeye eklemeyi hatırlamasa bile kapı kırılır.

Sonucu açıkça: **bir dağıtım siteyi kendiliğinden açmaz.** Yayın kararı elle
uygulanır (bkz. §5).

## 3. Yayına alınan sayfalar — on sekizi de adıyla

Karar sahibi: **Zabuno sahibi.** Gün: **2026-09-08.** Ortak sebep: kurumsal
sitenin ve altbilgi/pSEO yüzeyinin açılması. Her satırın kendi sebebi
`config/content-publication-decisions.php` içinde yazılıdır.

| # | Adres | Sayfa |
|---|---|---|
| 1 | `/en/product` | Product overview |
| 2 | `/en/product/qr-menu` | QR menu |
| 3 | `/en/product/menu-management` | Menu management |
| 4 | `/en/product/menu-management/categories` | Categories |
| 5 | `/en/product/menu-management/dishes` | Dishes |
| 6 | `/en/product/menu-management/prices` | Prices |
| 7 | `/en/product/menu-management/stock-status` | Stock status |
| 8 | `/en/product/menu-management/versions-and-rollback` | Versions and rollback |
| 9 | `/en/product/tables-and-qr-codes` | Tables and QR codes |
| 10 | `/en/product/analytics` | Analytics |
| 11 | `/en/product/zabuno-ai` | Zabuno AI |
| 12 | `/en/product/images-and-media` | Images and media |
| 13 | `/en/product/languages-and-currency` | Languages and currency |
| 14 | `/en/product/multiple-branches` | Multiple branches |
| 15 | `/en/product/design-and-branding` | Design and branding |
| 16 | `/en/product/ordering` | Ordering |
| 17 | `/en/solutions` | Solutions |
| 18 | `/en/pricing` | Pricing |

### Yayınlanmayanlar — ve neden

**386 Türkçe kütük satırı.** Hiçbirinin metni yok ve çeviri kilidi kapalı
(`docs/120` §7). Bir tanesini bile yayına almak, ziyaretçiye 404 vaat eden
bir bağlantı doğururdu: kütükte "yayında" yazsa bile son emniyet kemeri
(`ResolvePageDelivery`) metinsiz bir sayfayı 404'e düşürür. Yayınlanmış
GÖRÜNÜP 404 dönen bir sayfa, hiç yayınlanmamış olandan kötüdür — arama
motoru onu bulur, ziyaretçi tıklar, ve ikisi de boş bir odaya girer.

Komut bunu iddia etmiyor, **ölçüyor**: metni olmayan bir satır karara
yazılırsa komut hiçbir şey yapmadan durur (`PUBLISH-DECISION-04`).

## 4. Ölçülen sonuçlar

Temiz bir veritabanında, `site:import-map` + `site:apply-publication-decisions`
koşturularak ölçüldü.

| Ölçüm | Önce | Sonra |
|---|---|---|
| Altbilgideki grup sayısı | 2 | **5** (2 yaşayan + 3 pSEO) |
| Altbilgideki benzersiz bağlantı | 12 | **30** |
| pSEO bandı | çizilmiyor | **3 grup, 18 bağlantı** |
| `sitemap.xml` adres sayısı | 9 | **27** |
| Altbilgideki bağlantıların HTTP kodu | 12/12 → 200 | **30/30 → 200** |

pSEO bandının üç grubu, sayfaların KENDİ hiyerarşisinden çıkıyor — elle
yazılmadı: **Explore** (3), **Product overview** (10), **Menu management**
(5).

### 320 pikselde ne oldu

Dar ekran taban (`TOUCH-FIRST-INTERFACE`). Ölçüm gerçek bir düzen motorunda,
320×480'de (iPhone 4 tabanı) yapıldı:

| Ölçüm | Önce | Sonra |
|---|---|---|
| Altbilginin yüksekliği (bant kapalı) | 797 px — 1,66 ekran | **858 px — 1,79 ekran** |
| Altbilginin yüksekliği (bant açık) | — | 1886 px — 3,93 ekran |
| Yatay taşma | yok | **yok** (belge genişliği 320 = görüntü alanı) |

Bant **kapalı başlıyor** ve bu yüzden on sekiz bağlantının dar ekrana
maliyeti 61 piksel: bir satırlık "Browse all pages" düğmesi. Parmakla açan
kişi 3,93 ekranlık bir liste alıyor — ama açmayı kendisi seçiyor. Bandın
içeriği yine de sunucu HTML'inde duruyor, yani arama motoru ve betik
çalıştırmayan istemciler onu açmadan görüyor (`FOOTER-CONTENT-05`).

### Yayın bir kusuru görünür kıldı — ve kapatıldı

İki kusur ancak sayfalar açıldığında ölçülebilir hâle geldi. İkisi de bu
pakette kapandı:

1. **Altbilgi etiketleri Türkçe olacaktı.** Kütükteki `title` alanı site
   haritası BELGESİNDEN geliyor ve o belge Türkçe — kaynak dil satırları için
   bile. Üstelik bir kısmı başlık bile değil, bir açıklama cümlesi:
   `/en/product/qr-menu` satırının başlığı *"QR, dijital, mobil ve temassız
   menü özelliklerini tek sayfada anlatır"*. İngilizce bir sitenin altbilgisi
   Türkçe cümlelerle dolacaktı. Etiket artık sayfanın KENDİ yazılmış kısa
   adından geliyor (`breadcrumbTitle`). **Çeviri yapılmadı**: o kısa ad zaten
   depoda yazılıydı ve ziyaretçi onu sayfanın içinde de görüyor.

2. **Ekmek kırıntısı bağlantısı parmakla basılamıyordu.** Ölçüldü
   (`scripts/mobile-ux-audit`, 320×568): 112×20 piksel. Asgari 44×44.
   20 piksel bir imleç için yeter, parmak için yetmez — hedefe basamayan
   kişi geri gidemez. Aynı şablon ailesinde zaten kullanılan çözüm uygulandı
   (`inline-flex min-h-[44px]`).

Mobil denetim, düzeltmeden sonra: **18 yeni sayfa, sıfır yeni ihlal.**

| Genişlik | Önce (18 sayfa) | Sonra (36 sayfa) |
|---|---|---|
| 320×568 | 3 sayfa etkilenmiş, 6 bulgu | **3 sayfa etkilenmiş, 6 bulgu** |
| 1280×800 | 3 sayfa etkilenmiş, 6 bulgu | **3 sayfa etkilenmiş, 6 bulgu** |

Kalan altı bulgu bu paketten ÖNCE de vardı (ana sayfa ve `/help` içindeki
metin içi bağlantılar) ve bu paketin kapsamı dışındadır.

## 5. Nasıl uygulanır

Kütük dolduktan sonra, tek komut:

```
php artisan site:apply-publication-decisions
```

Önce ne olacağını görmek için `--dry-run`. Komut **yıkıcı değildir** ve
tekrar çalıştırmak zararsızdır: bir kez yayınlanmış bir satıra bir daha
dokunmaz, dolayısıyla sahibin SONRADAN verdiği bir kararı sessizce geri
almaz.

Sunucuda sıra şudur: `php artisan site:import-map` (kütüğü doldurur, her
dağıtımda kendiliğinden koşar) → `php artisan site:apply-publication-decisions`
(yayın kararını uygular, **elle**).

## 6. Nasıl geri alınır

Sahip fikrini değiştirirse, tek komut:

```
php artisan site:apply-publication-decisions --rollback
```

On sekiz sayfa aynı anda gezintiden ve `sitemap.xml`'den çıkar; altbilginin
pSEO bandı hiç çizilmez. Ziyaretçiye 503 döner — "bu sayfa vardı, kısa
süreliğine yok" — ve arama motoruna indeksteki hâlini koruması söylenir.

Geri almanın geri alınması da tek komut:

```
php artisan site:apply-publication-decisions --restore
```

### Neden 503, neden taslağa dönülmüyor

Durum makinesi yayından taslağa dönmeye izin VERMEZ ve vermemeli:
yayınlanmış bir adres yayınlanmıştır, arama motoru onu görmüştür.
"Hiç yayınlanmamış gibi yap" diyen bir geri alma, kütüğü yalancı yapardı.
`maintenance` dürüst olanıdır: sayfa yüzeyden çıkar, geçmişi durur, ve geri
gelebilir.

Bir sayfayı KALICI olarak kapatmak ayrı bir karardır (`retired`, 404) ve bu
komutun işi değildir — geri dönüşü olmayan bir kapanışı bir bayrağa
bağlamak, yanlışlıkla basılabilen bir düğme yapmak olurdu.

## 7. Değişen dosyalar

| Dosya | Ne oldu |
|---|---|
| `config/content-publication-decisions.php` | Yeni: on sekiz sayfa adıyla, sebebiyle, karar sahibiyle |
| `app/Domain/Content/PublicationDecision.php` | Yeni: eksik bir kararın doğamaması |
| `app/Console/Commands/ApplyPublicationDecisionsCommand.php` | Yeni: kararı uygular, geri alır, geri getirir |
| `app/Support/Site/SiteNavigation.php` | Altbilgi etiketi sayfanın kendi yazılmış kısa adından |
| `resources/views/content/page.blade.php` | Kırıntı bağlantısı 44 piksel dokunma hedefi |
| `tests/Feature/Content/ApplyPublicationDecisionsCommandTest.php` | Yeni: on kapı |
| `tests/Feature/PublicSite/PublishedPagesSurfaceTest.php` | Yeni: beş kapı — gerçek kararlar uygulandığında yüzey |
| `tests/Feature/Deployment/DeploymentContractTest.php` | Yasak genişletildi ve ölçüme bağlandı (`DEPLOY-PAGE-LEDGER-13`) |

`SyncContentStatusCommand` tavanına dokunulmadı. Hiçbir çeviri üretilmedi,
hiçbir çeviri işi kuyruklanmadı. Türkçe kütük satırlarının hiçbiri
kıpırdamadı.

## 8. Bu paketin ölçemedikleri — açıkça

1. **Üretim sunucusundaki kütüğün bugünkü hâli bilinmiyor.** Sunucuya erişim
   yok. Ölçümlerin hepsi temiz bir veritabanında yapıldı. `site:import-map`
   her dağıtımda koştuğu için satırların orada olması BEKLENİYOR, ama
   ölçülmedi.

2. **Sayfalar canlıda henüz açılmadı.** Bu paket kararı ve onu uygulayan
   komutu getiriyor; komutun üretimde çalıştırılması ayrı ve bilinçli bir
   adımdır (§5). Bu bir eksik değil, §2'deki kararın gereği: bir dağıtım
   yayın kararı vermez.

3. **iOS Safari'de ölçülmedi.** Mobil denetim Chrome'da koşuyor
   (`scripts/mobile-ux-audit` kendi sınırını böyle yazıyor).

4. **İçeriğin kendisi bu pakette okunmadı.** Ölçülen şey metnin VAR olduğu;
   metnin doğru olduğu değil. O, sayfaları yazan paketlerin (FF-191/192/203/229)
   ve sahibin işidir.
