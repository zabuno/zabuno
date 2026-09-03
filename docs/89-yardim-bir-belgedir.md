# 89 — Yardım bir belgedir, etiket değil (P1-01 tamamlandı)

## Canlıda görülen iki kusur

`docs/88` fiyat ve iletişim sayfalarını açtı. Canlıda ilk bakışta iki şey
çıktı:

**Sekme başlığı "— Zabuno" idi.** Metni kataloğa taşırken `@section('title')`
düşmüştü. Bu sayfalar tam olarak **paylaşılmak** için var — fiyatı biri
arkadaşına gönderir — ve paylaşılan bağlantı hangi sayfa olduğunu
söylemiyordu.

**"Pricing" iki kez yazıyordu:** sayfanın `<h1>`'i ve bölümün `<h2>`'si. Aynı
kelimeyi üst üste iki başlıkta göstermek, ekran okuyucuda iki ayrı bölüm
varmış gibi okunur.

Bölümün başlık etiketi artık çağırana bırakılıyor: ana sayfada `h2`, kendi
sayfasında `h1`. Bir kapı her genel sayfa için **tam bir `<h1>`**, boş
olmayan bir başlık ve bir açıklama arıyor — altı sayfanın hepsinde.

## Yardım: neden katalog değil, dosya

`docs/88`'de bu maddeyi ertelemiştim ve sebebini yazmıştım: sayfa 40'tan fazla
cümle taşıyor.

**Cümle başına katalog anahtarı makaleler için yanlış şekildir.** Çevirmen
bağlamı göremez; bir paragrafı ikiye bölmek anahtar listesini bozar; gözden
geçiren metni bir bütün olarak okuyamaz. Arayüz etiketi ile makale aynı şey
değil ve aynı mekanizmaya zorlamak ikisini birden bozar.

Makaleler **dile göre dosya** olarak yaşıyor — her dokümantasyon sitesinin
yaptığı gibi:

```
resources/help/en/first-15-minutes.blade.php
resources/help/tr/first-15-minutes.blade.php
```

Dosyalar `resources/views` **dışında** duruyor ve ayrı bir görünüm alanı
olarak kaydediliyor. Sebep şekilsel: çevrilemez-dize sayacı **arayüz
şablonlarını** ölçer ve bir makaleyi orada saymak ölçümü anlamsızlaştırırdı.

Bu bir kaçış değil, bir takas — ve karşılığı bir kapı: **desteklenen her dilin
dosyası var olmak zorunda.** Eksik bir dil, o dili seçen kullanıcıya sessizce
İngilizce gösterirdi ve kimse fark etmezdi. Kapı eksikliği kullanıcıya değil
CI'a gösteriyor.

## `lang` sayfanın dilidir

Yardım makalesi okuyucunun dilinde geliyor. Düzen `<html lang>` için
uygulamanın dilini kullanıyordu; Türkçe metin `lang="en"` ile sunuluyordu ve
ekran okuyucu onu İngilizce telaffuz ederdi. Sayfa artık kendi dilini
bildiriyor.

## Makale gerçek ekranlara işaret eder

Var olmayan bir ekranı tarif eden yardım, kullanıcıyı **ikinci kez** tıkar:
önce özelliği bulamaz, sonra yardımın da yanıldığını görür ve bir daha açmaz.

Bir test makalede `CSV`, `Publication` ve `Sold out` geçmesini arıyor —
üçü de bu turlarda açılmış gerçek yüzeyler (`docs/80`, `docs/81`, `docs/82`).

## Kanıt

`PublicPageIdentityTest` (19), `HelpContentTest` (6)

| Requirement | Ne donduruluyor |
| --- | --- |
| `PUBLIC-PAGE-TITLE-01` | Her genel sayfanın kendi adı var; "— Zabuno" tek başına ad değil |
| `PUBLIC-PAGE-DESCRIPTION-01` | Her sayfa kendini tarif eder |
| `PUBLIC-PAGE-SINGLE-H1-01` | Tam bir `<h1>`; "Pricing" bir kez |
| `HELP-NO-AUTH-01` | Yardım oturum istemez |
| `HELP-THREE-QUESTIONS-01` | Üç sorunun her biri kendi çıpasında |
| `HELP-POINTS-AT-REAL-SCREENS-01` | Var olan ekranlara işaret eder |
| `HELP-EVERY-LOCALE-01` | Desteklenen her dilin makalesi var |

## Ürün iddiası

Çalışır: kaydolmamış bir ziyaretçi fiyatı görür, mesaj yazar ve ilk oturumda
takılacağı üç soruyu kendi dilinde okur.
