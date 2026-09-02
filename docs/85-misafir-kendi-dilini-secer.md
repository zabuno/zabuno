# 85 — Misafir kendi dilini seçer (P1-06)

## Önce ne oluyordu

Turistik bir restoranda misafirin yarısı Türkçe okumaz. Misafir sayfasının
arayüz metinleri Blade şablonuna **sabit Türkçe** yazılmıştı ve misafir için
dil değiştirme yolu yoktu.

## İki katman, iki ayrı karar

Bu ayrım paketin bütün kararlarını belirliyor:

| Katman | Bu pakette | Neden |
| --- | --- | --- |
| **Arayüz metinleri** ("Menüde ara", "Bugün tükendi") | Katalogdan gelir, çevrilir | Ürünün metni; bizim yazdığımız |
| **Menü içeriği** (ürün adları, açıklamalar) | **Çevrilmez** | Restoranın metni; ayrı ve çok daha büyük bir iş |

İkisini karıştırmak, arayüzü İngilizceye alan misafire menünün de İngilizce
olacağını ima ederdi — **tutulmayacak bir söz**. Bu yüzden dil değişince
sayfada şu yazıyor:

> Dish names are in the restaurant's own language.

Arayüz zaten içerik diliyle aynıysa bu not **gösterilmez**: söylenecek bir şey
yok ve her sayfada duran bir not okunmaz hâle gelir.

## Şablonda tek bir sabit metin yok

Bir test bunu donduruyor — ve **iki yeri** birden tarıyor:

1. Şablon işaretlemesi (borç tarayıcısıyla).
2. **Betik gövdesi**: `<script>` içindeki dizeler de kullanıcı metnidir ve
   borç tarayıcısı onları atlıyor.

Betiğin metinleri, sunucunun bastığı bir JSON haritasından okunuyor. Şablonda
tek tek çağırmak yerine harita verilmesinin sebebi tam olarak bu testin
yazılabilmesi: metin şablonda değil, katalogda yaşıyor.

Harita **verilmezse görünüm onu kendi çözer**. Aksi hâlde haritayı geçirmeyi
unutan bir çağıran, sayfayı sessizce **boş etiketlerle** basardı — ve bu,
ekranda görülene kadar fark edilmezdi.

## `lang` arayüzün, içerik kendi dilini taşır

```html
<html lang="en">            <!-- arayüz -->
  …
  <section class="qr-menu-category" lang="tr">   <!-- menü içeriği -->
```

İkisini tek etikete sıkıştırmak, ekran okuyucunun ya arayüzü ya ürün adlarını
yanlış telaffuz etmesi demekti.

## Seçim düz bir bağlantı

Dil seçimi `?lang=en` bağlantısıdır: JavaScript çalışmasa da çalışır. Seçim
**çerezde** hatırlanır, böylece sayfa daha ilk boyamada doğru dilde gelir —
misafir her açılışta yeniden seçmez.

Tarayıcının `Accept-Language` başlığı **kullanılmaz**. Aynı karekodu okutan
iki kişinin farklı sayfa görmesi, sahibin "menümde ne yazıyor" sorusunu
cevapsız bırakırdı; seçimi görünür kılmak, tahmin etmekten dürüsttür.

Desteklenen dil listesi **kısadır ve kasten**: her dil, doldurulması gereken
bir çeviri dosyası demektir ve yarısı boş bir dil seçici, misafire çalışmayan
bir söz verir.

## Yol boyunca iki gerçek kusur

### 1. Dil seçimi kanonik yönlendirmede kayboluyordu

Misafir dil bağlantısına bastığında istek slugsuz adrese gidiyor ve oradan
kalıcı adrese yönleniyor. Sorgu düşüyordu — yani **düğme çalışmıyor
görünüyordu**. Yönlendirme artık `lang`'i koruyor.

### 2. `guest` alanını Türkçe kaynakla açmak yanlıştı

`docs/82`'de bu alanı Türkçe kaynakla açmıştım; gerekçesi "misafir sayfası
restoranın dilidir" idi. Boru hattı `en`'i kaynak sayıyor: `guest.en.po`
Türkçeyi taşıyor, `guest.tr.po` boş kalıyor ve **her dil Türkçe
gösteriliyordu**. Dil seçici çalışıyor gibi görünüp yalan söylerdi.

Alan artık diğer bütün alanlarla aynı: **kaynak İngilizce**. Sayfanın bugüne
kadar taşıdığı Türkçe cümleler `lang/po/guest.tr.po` içine **olduğu gibi**
taşındı — bunlar çeviri değil, ürünün zaten sahip olduğu metinler.

## Yorumlar kullanıcı metni değildir

Betik taramasının ilk hâli, Türkçe yazılmış **yorumları** ihlal sayıyordu —
yani kararın gerekçesini yazmayı cezalandırıyordu. Yorumlar önce düşürülüyor.
Aynı ders bu depoda üçüncü kez öğrenildi (`docs/82`'deki iki mimari kapı).

## Kanıt

`tests/Feature/QrDestination/GuestLanguageTest.php` (4)

| Requirement | Ne donduruluyor |
| --- | --- |
| `GUEST-I18N-NO-HARDCODED-01` | Şablonda ve betik gövdesinde sabit kullanıcı metni yok |
| `GUEST-I18N-SWITCH-01` | Dil değişir; arayüz metinleri gerçekten çevrilir |
| `GUEST-I18N-REMEMBERED-01` | Seçim aynı cihazda hatırlanır |
| `GUEST-I18N-LANG-DIR-01` | `lang`/`dir` seçime göre; desteklenmeyen dil düşer, kırılmaz |
| `GUEST-I18N-CONTENT-HONEST-01` | Ürün adları çevrilmez ve bu açıkça söylenir |

Çevrilemez dize borcu 69 → 62 düştü.

## Ürün iddiası

Çalışır: misafir aynı karekoddan girip dili değiştirir, seçimi hatırlanır ve
arayüz gerçekten çevrilir.
Çalışmaz: menü İÇERİĞİ çevrilmiyor — ürün adları restoranın dilinde kalıyor
ve sayfa bunu söylüyor. İçerik çevirisi ayrı bir karar
(`modules/opt-04-multi-language-content.md`) ve gerçek talep ölçülmeden
yapılmamalı.
