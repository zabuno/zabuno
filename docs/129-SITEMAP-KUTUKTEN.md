<!--
    KARAR BELGESİ — FF-214, 2026-09-07.

    Ölçüm önce yapıldı, karar sonra verildi. Belgedeki her sayı gerçekten
    koşturulmuş bir komuttan gelir; ölçülemeyen şeyler §7'de AÇIKÇA
    "bilinmiyor" diye ayrılmıştır.
-->

# Sitemap kütükten türer

## 1. Sahibin gördüğü şey

Sahip bir kurumsal sayfayı yayına alır. Sayfa açılır, adres çalışır, metin
görünür. Ama Google onu **bulmaz** — ve bulmamasının sebebi sayfada değil,
sayfanın adının hiçbir yerde geçmemesindedir.

## 2. Zincirin hangi halkası kopuktu

Bir sayfanın arama sonuçlarına düşmesi için beş halka gerekiyor:

| # | Halka | Nerede yaşar | Durum (ölçüm öncesi) |
|---|---|---|---|
| 1 | Sayfanın **metni** | Kodda (`ProductPageLibrary`) | ✅ on altı sayfa yazılmış |
| 2 | Sayfanın **kütük kaydı** | `content_pages` tablosu | ✅ (`docs/128`, FF-211) |
| 3 | Sayfanın **yayın durumu** | Aynı tablonun bir alanı | ⏸ sahibin kararını bekliyor |
| 4 | **Kapı** — durumu okuyup 200/404 veren kod | `ShowCorporatePageController` | ✅ çalışıyor |
| 5 | **İlan** — "bu adres var" demek | `sitemap.xml` | ❌ **kütüğü hiç okumuyordu** |

Kopuk halka **5**'ti.

### Somut yolculuk

Menü sayfalarına iç bağlantı yoktur; kurumsal sayfalar da yenidir ve dışarıdan
kimse onlara bağlantı vermemiştir. Yani arama motorunun bu sitede bir adresi
öğrenmesinin **tek yolu** `sitemap.xml`tir.

O dosya ise dört adresi (`/`, `/terms`, `/privacy`, `/kvkk`) koda sabit
yazıyordu ve üzerine yalnız yayınlanmış restoran menülerini ekliyordu. Kütüğe
hiç bakmıyordu. Sonuç: sahip "Karekod menü" sayfasını yayına aldığında sayfa
ziyaretçiye açılacak, ama arama motoruna **hiç ilan edilmeyecekti**.

Kusurun asıl kötü yanı sessiz oluşuydu. Sitemap her zaman geçerli bir XML
döndürüyor, istek 200 dönüyor, hiçbir kapı kırmızıya dönmüyor. Eksik olan şey,
orada **olmayan** bir satır. `docs/128` §6.4 bunu ölçüp kayda geçirmişti; bu
paket odur.

## 3. Karar: tek karar noktası, iki soru

Bir adresin sitemap'e girip girmemesi ile o adresin **200 mü 404 mü** döndüğü
artık **aynı nesneden** okunuyor: `PageRenderDecision`.

`PageGate` zaten "tek karar noktası"ydı ve nesnesi zaten hem `statusCode` hem
`includeInSitemap` taşıyordu. Eksik olan, o nesneyi **üreten yolun** tek
olmasıydı. Kapıya varmak için üç şeyin daha bilinmesi gerekiyor:

1. Satır bir sayfa mı? (şablon ve dış bağlantı sayfa değildir)
2. O dilde gerçekten yazılmış bir metin var mı?
3. Metin yoksa gerçek aşama nedir?

Bu üç adım denetleyicide vardı, `ResolveLocaleAlternates`'te ikinci kez
yazılmıştı, "ilgili sayfalar" süzgecinde üçüncü kez, ve sitemap'te **hiç**
yoktu. Dört yer, dört kural.

Hepsi tek bir sınıfa taşındı: **`App\Application\Content\UseCase\ResolvePageDelivery`**.
Dördü de artık onu çağırıyor. İki ayrı yerde iki ayrı kural bir gün ayrışır ve
sitemap olmayan sayfaları ilan eder — arama motoruna yalan söylemektir bu.

Kapı testi tek cümledir ve `SitemapRegistryTest` onu uçtan uca ölçer:
**sitemap'te duran her adres 200 dönmelidir.**

## 4. Sabit yazılı adresler — neden sabit KALDILAR

Ölçüldü: temiz bir veritabanında `site:import-map` koşturuldu ve sabit dokuz
yolun **hiçbiri** `content_pages` tablosunda çıkmadı.

Çıkmamalıydı da. Bu dokuz adres kurumsal kapıdan geçmiyor:

- `/` → `FoundationStatusController` (kendi rotası)
- `/terms`, `/privacy`, `/kvkk`, `/distance-sales`, `/pre-information`,
  `/refund-policy`, `/cookies`, `/marketing-consent` → `ShowLegalDocumentController`
  (FF-198, sekiz belge tek denetleyici)

Bunların **yayın durumu yoktur**, çünkü yayın kararı zaten verilmiş: sayfa
canlı ve bugün 200 dönüyor. Kütükten gelmeyen bir adres, kütüğün kararına da
tabi değildir. Dolayısıyla sabit kalmaları bir istisna değil, bir **sınır**.

Yine de **aynı adres iki kez listelenmez**: biri bir gün kütüğe taşınırsa
tekrar süzgeci onu tek satıra indirir. Bugün için değil, o gün için.

**Ölçülüp DOKUNULMAYAN bulgu.** `/pricing`, `/help` ve `/contact` de bugün 200
dönen canlı adreslerdir ve sitemap'te **yoktur**. Bu paket onları eklemedi:
hangi adresin ilan edileceği bir yayın kararıdır ve yayın kararı insanlarındır.
Burada yalnız kayda geçiriliyor.

## 5. Ölçek: tek dosya, dizin DEĞİL

Sınır ikilidir: **50.000 URL** ve **50 MB** (sıkıştırılmamış). Ölçülen bugünkü
hâl:

| Ölçüm | Sayı |
|---|---|
| Kütük satırı | 402 (386 `tr`, 16 `en`) |
| Bunlardan gerçek sayfa | 326 (73 şablon, 3 dış bağlantı hariç) |
| Bugün sitemap'te duran adres | **9** |
| Bugünkü dosya boyutu | **685 bayt** |

Dokuz, çünkü kütükteki **hiçbir sayfa yayınlanmış değil** — içe aktarma
kayıtları `planned` olarak yaratır, `site:sync-content-status` ise on altısını
yalnız `content_draft`a taşır. Sitemap'in bu sayıyı değiştirmemesi doğrudur:
**bu paket hiçbir sayfayı yayına almaz.**

Tavan da ölçüldü. Ayrı bir ölçüm veritabanında 326 gerçek satırın hepsi
`published` işaretlendi ve sitemap yeniden üretildi: **25 adres, 1891 bayt.**
On altı, çünkü geri kalan 310 satırın o dilde yazılmış bir metni yok ve
metinsiz bir sayfa 404 döner.

**Karar: tek dosya.** 50.000 sınırına 1891 baytlık bir dosyayla 0,004 oranında
yaklaşılmış durumda ve URL sayısı sınırın binde ikisi. Erken bölmek, hiçbir
sorunu çözmeyen bir dolaylılık katmanı eklemek olurdu — ve sitemap dizini,
tarayıcıya bir istek yerine iki istek yaptırır.

**Bölme günü ölçülebilir bir eşiktir** ve o eşiğe kurumsal sayfalar değil,
yayınlanan **menüler** yaklaşır: her indekslenebilir menü bir URL'dir. 50.000
URL'de dosya kabaca 5 MB olur, yani **URL sayısı 50 MB'tan önce dolar**. İzlenecek
sayı budur.

## 6. Çok dillilik: var olmayan bir dil ilan edilmez

hreflang bir nezaket etiketi değil bir **iddiadır**: "bu sayfanın şu dildeki
karşılığı şuradadır." Yanlış ilan edilen bir alternatif, arama motorunu
çalışmayan bir adrese gönderir.

Bu depoda risk somut. 386 Türkçe kütük satırının yalnız **16**'sının kaynak dil
(İngilizce) karşılığı yazılmış (`config/site-source-paths.php`); geri kalan 370
sayfanın `/en/...` adresi **yoktur**. Anahtardan mekanik bir adres üretmek
mümkündü — ve o adres bir 404 vaadi olurdu.

Sitemap bu yüzden kendi listesini kurmuyor: var olan `ResolveLocaleAlternates`
kullanılıyor, o da artık aynı `ResolvePageDelivery`'den geçiyor. Ölçüldü: 326
satırın hepsi `published` işaretlendiğinde bile üretilen `xhtml:link` sayısı
**sıfır** — çünkü karşılıkların metni yok, dolayısıyla ilan edilecek bir
karşılık da yok.

İki dil gerçekten açıldığında ilan karşılıklı verilir ve `x-default` kaynak dili
gösterir (`docs/118` E4). `SitemapRegistryTest` bunu ayrı bir testle sabitliyor.

## 7. Bu paketin ölçemedikleri — açıkça

1. **Üretimde bugün kaç adres listeleniyor, bilinmiyor.** Üretim sunucusuna
   erişim yok. Ölçülen sayı (9), temiz bir veritabanına `site:import-map`
   koşturulmuş yerel bir ortamdan gelir. Üretimdeki sayı yalnız yayın
   durumlarına bağlı olarak farklı olabilir.

2. **Arama motorunun bu sitemap'i nasıl işlediği ölçülmedi.** Search Console
   bağlanmadı; `xhtml:link` uzantısı Google'ın belgelenmiş biçimine göre
   yazıldı ama gerçek bir tarama ile doğrulanmadı.

3. **`lastmod` bugün hiçbir kurumsal sayfada dolmuyor.** `content_pages` tablosu
   `published_at` alanını taşıyor ama onu dolduran hiçbir kod yok
   (`site:import-map` doldurmaz). Tarih uydurmak yerine alan atlanıyor; alanı
   kimin dolduracağı ayrı bir paketin işi.

## 8. Değişen dosyalar

| Dosya | Ne oldu |
|---|---|
| `app/Application/Content/PageDelivery.php` | YENİ — bir kütük satırı hakkındaki tek karar |
| `app/Application/Content/UseCase/ResolvePageDelivery.php` | YENİ — o kararı üreten tek yol |
| `app/Http/Controllers/Seo/ShowSitemapController.php` | Kütükten türüyor, hreflang veriyor, tekrarı süzüyor |
| `app/Http/Controllers/Content/ShowCorporatePageController.php` | Kendi kopyası yerine tek kararı çağırıyor |
| `app/Application/Content/UseCase/ResolveLocaleAlternates.php` | İkinci kopyası yerine tek kararı çağırıyor |
| `tests/Feature/Seo/SitemapRegistryTest.php` | YENİ — 17 test |
| `docs/129-SITEMAP-KUTUKTEN.md` | Bu belge |

## 9. Rapor alanları

- **once:** Sahip bir kurumsal sayfayı yayına alsa bile `sitemap.xml` ondan
  haberdar olmuyordu; dosya dört adresi sabit yazıp yayınlanmış menüleri
  ekliyordu. Sitemap'e giren adresle 200 dönen adres iki ayrı yerde
  hesaplanıyordu (aslında dört ayrı yerde).
- **simdi:** Sitemap kütükten türüyor ve üyelik kararı, ziyaretçinin aldığı
  HTTP kodunu üreten `PageRenderDecision` nesnesinin ta kendisinden okunuyor.
  Taslak, planlanan, onaylı, emekli, bakımda, şablon, dış bağlantı ve metni
  yazılmamış sayfa girmiyor; var olmayan bir dil ilan edilmiyor; aynı adres
  iki kez yazılmıyor.
- **fark:** Bugün listelenen adres sayısı **değişmedi (9)** — ve bu doğru
  sonuçtur, çünkü kütükte yayınlanmış tek bir sayfa yok. Değişen şey, yayın
  kararı verildiği anda sitemap'in onu **otomatik** yansıtacak olması.
- **kullaniciYolculugu:** Sahip panelde "Karekod menü" sayfasını yayına alır.
  Kapı o andan itibaren 200 döner ve **aynı karar** sayfayı `sitemap.xml`e
  yazar. Google dosyayı bir sonraki taramasında okur, adresi öğrenir, sayfaya
  gelir, `X-Robots-Tag: index,follow` görür ve indeksler. Daha önce bu zincir
  beşinci halkada kopuyordu: sayfa açılıyor ama adı hiçbir yerde geçmiyordu.
- **kalanEngel:** Kütükte yayınlanmış sayfa yok — bu bir kusur değil, bekleyen
  bir **insan kararı**. `lastmod` alanını dolduracak bir yayın damgası da yok
  (§7 madde 3). Üretimdeki gerçek sayı ölçülemedi (§7 madde 1).
- **capability_delta:** `sitemap.xml` artık sayfa kütüğünün bir projeksiyonudur;
  bir yayın kararı ile arama motorunun gördüğü ilan arasında elle yapılacak
  hiçbir adım kalmadı.
- **Çalışabilen:** Bir kurumsal sayfa `published` işaretlendiği ve o dilde metni
  yazıldığı anda sitemap'te görünür; karşılığı olan diller `hreflang` ile ilan
  edilir.
- **Çalışamayan:** Hiçbir kurumsal sayfa bugün yayında değil, dolayısıyla bugün
  sitemap'te hiçbir kurumsal sayfa yok. Bunu bu paket açamaz ve açmamalıdır.
