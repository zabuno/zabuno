# 84 — Menü mühendisliği: olay taksonomisi (P1-08)

## Önce iş sorusu, sonra olay

`docs/61` H2'nin kendi gerekçesi buydu ve tersini yapmak, "ölçebildiğimizi
ölçüp sonra ona bir anlam aramak" olurdu. Aşağıdaki tabloda **önce soru**
var; olay, sorunun cevabını verebildiği için var.

| Sahibin sorduğu soru | Cevabı veren olay | Neden bu olay yeter |
| --- | --- | --- |
| "Menümü kaç kişi açtı?" | `menu_open` | Zaten vardı |
| "Karekodu kaç kişi okuttu, kaçı menüye ulaştı?" | `qr_resolve` → `menu_open` | Zaten vardı (`docs/68` huni) |
| **"Hangi ürüne bakılıyor?"** | **`item_view`** | Menü mühendisliğinin tek girdisi: hangi ürün ilgi çekiyor |
| **"Hangi ürüne hiç bakılmıyor?"** | `item_view`'ün **yokluğu** | Ayrı bir olay gerekmez; yayındaki ürün listesiyle fark alınır |
| **"Misafir ne arıyor ama bulamıyor?"** | **`search_no_results`** | Menüde olmayan talebi gösterir — sahibin göremediği tek talep |

Dört soru sorulmadı, dolayısıyla dört olay **yok**: hangi kategoriye
girilmediği (`item_view` zaten kategoriyi ima eder), kaydırma derinliği,
sayfada kalma süresi ve cihaz/tarayıcı kırılımı (`docs/69`: post-MVP).

## "Görüntülenme" ne demek

Bir ürün, **ekranda gerçekten görünürse** görüntülenmiş sayılır — sayfa
açıldığında listede olması yetmez. Kırk ürünlük bir menüde her açılışta kırk
görüntülenme saymak, "hangi ürün ilgi çekiyor" sorusunu cevapsız bırakırdı:
bütün ürünler eşit görünürdü.

Ölçüt: ürün satırının en az yarısı, en az **bir saniye** ekranda kalır.
Kaydırırken hızla geçilen bir satır sayılmaz.

Aynı ziyaretçi aynı ürünü aynı gün kaç kez görürse görsün **bir** sayılır.
Sayılan şey ilgi, kaydırma alışkanlığı değil.

## Misafir kimliği kurulmaz

Ölçüm, `docs/68`'deki günlük dönen ve kiracıya özel tuzla türetilmiş
ziyaretçi anahtarını kullanır: ham IP ve tarayıcı bilgisi **saklanmaz**,
anahtar ertesi gün başka bir değere döner ve iki kiracı arasında
eşleştirilemez.

Arama terimi **yalnız sonuçsuz aramalarda** ve **kırpılmış** olarak yazılır.
Sonuçlu bir aramanın terimini saklamanın ürün karşılığı yok — ve saklanmayan
veri sızmaz.

## Sahtecilik

Uç nokta herkese açık: menüyü açan herkes olay gönderebilir, dolayısıyla
sayılar şişirilebilir.

Üç önlem:

1. Sayılan şey **ham vuruş değil, farklı ziyaretçi sayısıdır**. Aynı
   anahtardan gelen tekrarlar bir kere sayılır.
2. Olaylar yalnız **yayındaki** menü satırları için kabul edilir; başka bir
   kimlik gönderen istek sessizce düşer.
3. İstek sayısı ve tek istekteki olay sayısı sınırlıdır.

Bu, kararlı bir saldırganı durdurmaz — durdurduğunu iddia etmek yanlış olur.
Durdurduğu şey, sayıların kazayla ya da ucuz bir betikle anlamsızlaşması.

## Veri modeli

`analytics_events` genişletilir, ikinci bir tablo açılmaz: aynı soruların
cevapları aynı yerde durmalı, yoksa "toplam" iki kaynaktan toplanır ve
ayrışır.

- `qr_code_id` **null olabilir hâle gelir.** Kalıcı adresten (`/menu/{key}`)
  gelen bir misafirin karekodu yoktur — ve bu ziyaretler bugüne kadar hiç
  ölçülmüyordu.
- `menu_item_id` (null olabilir) — `item_view` için.
- `search_term` (null olabilir) — `search_no_results` için.

## Rapor eşiği

Rapor için en az **beş farklı ziyaretçi** gerekir. Eşiğin altında sayı
**gösterilmez**; ekranda sebep ve eşik yazar:

> Not enough visitors yet to rank your dishes: 2 of 5.

Üç ziyaretçinin baktığı bir ürünü "en çok bakılan" diye sunmak, sahibi
gürültüye göre menü düzenlettirirdi — ve bir kez yanlış çıkan rapor bir daha
okunmaz.

## Plan kısıtı arıza değildir

Planı raporlamayı içermeyen bir işletmede bölüm **hiç çizilmez**. "Yüklenemedi"
demek, sahibi ürünün bozulduğuna inandırırdı; oysa yapması gereken şey planını
yükseltmek.

## Yol boyunca çıkan üç bulgu

1. **Kalıcı adresten gelen ziyaretler hiç ölçülmüyordu.**
   `analytics_events.qr_code_id` zorunluydu ve `/menu/{key}` yolundan gelen
   misafirin karekodu yok — o yol olay yazamıyordu. Sütun artık null
   alabiliyor.

2. **Aralık iki yerde tanımlıydı.** Özet ve ürün raporu ayrı `match`
   bloklarından okusaydı, biri güncellenip diğeri unutulduğunda ekranda
   "toplam 10, ürünlerin toplamı 14" görünürdü. Tek yere alındı.

3. **Bileşen yanıt şekline körü körüne güveniyordu.** Beklenmedik bir gövde
   (eski önbellek, araya giren vekil, 200 dönen hata sayfası) analitik
   sayfasının **tamamını** çökertiyordu. Yanıt artık normalize ediliyor.

Ayrıca iki mevcut muhafız niyetini aşıyordu ve keskinleştirildi:

- *"Sayfada hiç `fetch(` olmasın"* kuralı, aramanın sunucuya sormasını
  yasaklamak için yazılmıştı. Sayfa artık ölçüm gönderiyor; kural yeniden
  ifade edildi — **sayfadaki ağ hedefi yalnız ölçüm ucudur** ve arama
  sonuçları DOM'dan gelir.
- *"Tazeleme tam bir istek atsın"* kuralı, sayfanın tek veri kaynağı olduğu
  gün yazılmıştı. Artık **özet ucu** sayılıyor: sahip "Tazele"ye bastığında
  sayfanın tamamı tazelenmeli.

## Kanıt

`tests/Feature/Analytics/MenuEngineeringTest.php` (7),
`MenuEngineeringRegion.test.tsx` (3)

| Requirement | Ne donduruluyor |
| --- | --- |
| `ITEM-VIEW-RECORDED-01` | Gerçekten görülen ürün sayılır |
| `ITEM-VIEW-DEDUPED-01` | Aynı ziyaretçi aynı gün bir kere; başka ziyaretçi ayrı |
| `ITEM-VIEW-ONLY-PUBLISHED-01` | Başka menünün satırı ya da uydurma kimlik düşer |
| `SEARCH-NO-RESULTS-01` | Terim normalize edilir; misafir kimliği kurulmaz |
| `MENU-ENGINEERING-REPORT-01` | En çok bakılan ve hiç bakılmayan ayrı ayrı |
| `MENU-ENGINEERING-THRESHOLD-01` | Veri yetersizse boş tablo değil, sebep ve eşik |
| `MENU-ENGINEERING-TENANT-01` | Bir restoranın sayıları başkasının raporunda görünmez |

## Ürün iddiası

Çalışır: sahip hangi ürüne bakıldığını, hangisine hiç bakılmadığını ve
misafirin arayıp bulamadığı şeyi görür; veri yetersizken ekran neden
yetersiz olduğunu ve eşiği yazar.
Çalışmaz: kategori girme oranı, kaydırma derinliği ve cihaz/tarayıcı kırılımı
— sorulmadıkları için ölçülmüyorlar (`docs/69`: post-MVP).
