# 108 — Medya yöneticisi: kanonik kaynak ve uygulama planı

**Kaynak:** `docs/reference/media-manager/` (sahibin verdiği master data,
2026-09-04). İkilemde o dosyalar kazanır.

**Sayaç:** 0/9 tamamlandı, 0/9 aktif.

## 1. Neden ayrı bir ekran

Depoda bugün medya, Ayarlar'ın yanında bir SAYFA: yükleme kartı solda,
kütüphane sağda. Kaynak ise kendi kabuğu olan bir UYGULAMA gösteriyor —
kendi başlığı, kendi arama alanı, kendi bölüm gezintisi ve solda **klasör
ağacı**.

Ayrım keyfi değil. Bir menüyü yönetmek ile bir dosya deposunu yönetmek
farklı işlerdir: birinde ürün ve fiyat, diğerinde biçim, boyut, sürüm, kota
ve kuyruk vardır. Aynı sayfaya sıkıştırıldığında ikisi de yarım kalıyordu.

## 2. Depoda ne var, ne yok

| Kaynak bölümü | Depoda karşılığı | Durum |
| --- | --- | --- |
| Kütüphane | `ListMediaController`, `MediaLibraryRegion` | var, **klasör yok** |
| Yükle | `StoreMediaController`, `MediaUploadRegion`, `ImageCropField` | var, **istemcide küçültme yok** |
| Dönüştür | — | **yok** |
| Görüntüle | `MediaAssetDetailDrawer` | kısmi, **PDF okuyucu yok** |
| Boyut motoru | `config/media-slots.php` (yalnız yapılandırma) | **arayüz yok**, kural düzenlenemiyor |
| Kuyruk | `media_processing_jobs` (tablo var) | **arayüz yok** |
| Kota ve çöp | `ShowMediaQuotaController`, `MediaTrashList` | var, **"yeri ne dolduruyor" yok** |
| Ayarlar | — | **yok** (klasör/ad deseni, güvenlik anahtarları) |
| Olgunluk | — | **yok** |

## 3. Sıralama ve gerekçesi

Sıra, sahibin işine göre: önce **var olanı bulunur kılan**, sonra **yeni
yeteneği açan**.

1. **Klasörler** (backend + kütüphane) — bugün elli fotoğraf tek bir düz
   listede duruyor. Klasör olmadan arama tek çare ve arama, adını
   hatırlamadığın dosyayı bulmaz.
2. **Kütüphane kabuğu** — süzme, sıralama, ızgara/liste, çoklu seçim.
3. **Yükleme sihirbazı** — istemcide küçültme, telefonla çekilen 8 MB'lık
   fotoğrafı ağa hiç sokmadan küçültür. Kota bundan doğrudan kazanır.
4. **Boyut motoru** — kural bir kez yazılır; bugün `config` dosyasında ve
   sahibin eli değmiyor. Yeniden üretim işi asılları korur.
5. **Kuyruk** — üçüncü ve dördüncü madde iş üretiyor; işin nerede olduğu
   görünmeden ikisi de "takıldı mı?" sorusunu doğurur.
6. **Kota ve çöp** — "yeri ne dolduruyor" kırılımı.
7. **Dönüştür** — modern biçime çevirme; aslı korunur.
8. **Görüntüle** — PDF ve görsel okuyucu.
9. **Ayarlar + Olgunluk**.

## 4. Değişmez kurallar (kaynağın kendi cümleleri)

- **Asıl korunur.** Dönüştürme ve yeniden üretim yeni SÜRÜM açar, hiçbir
  satır silinmez. Depodaki `media_versions` bunu zaten yapıyor.
- **Yeni kural yalnız yeni yüklemelere uygulanır.** Eskiler ancak açık bir
  yeniden üretim işiyle değişir.
- **Kota sessizce kesmez.** Sınıra yaklaşınca uyarı verilir.
- **İstemcide küçültme bir güvenlik kararı da:** dosya kullanıcının kendi
  makinesinde küçülür, sunucudan servis edilmez.

## 5. Halihazırda bilinen dağıtım engeli

`MEDIA_SCANNER_DRIVER` varsayılanı `unavailable`. Tarama temiz dönmeden
varlık `accepted` olmuyor, işlenmiyor, türev üretilmiyor — yani kütüphanede
önizleme çıkmıyor ve fotoğraf menüde kullanılamıyor. Bu bir ÜRÜN hatası
değil, sunucuda ClamAV kurulu olmamasıdır (`docs/102` §5m). Medya yöneticisi
ne kadar tamamlanırsa tamamlansın, o kurulmadan hat ölü kalır.

## 6. Kaynağın somut verisi (ajanlar yeniden türetmesin)

Aşağıdakiler `Medya Yonetimi v2.dc.html` içindeki gerçek veri
tanımlarından alınmıştır. Ekranda ne yazacağının kararı burada verilmiştir;
bir ajan bunları yeniden uydurmamalıdır.

### 6.1 Boyut motoru — türev kuralları

| Ad | Genişlik | Sığdırma | Kullanım | Biçimler |
| --- | --- | --- | --- | --- |
| thumb | 160 px | kırp | Liste satırı | AVIF, WebP |
| small | 320 px | sığdır | Menü kartı, telefon | AVIF, WebP |
| medium | 768 px | sığdır | Ürün ayrıntısı | AVIF, WebP, JPEG |
| large | 1440 px | sığdır | Tam ekran görsel | AVIF, WebP, JPEG |
| social | 1200 px | 1200×630 kırp | Paylaşım önizlemesi | JPEG |
| print | 2480 px | sığdır | QR kartı, afiş | JPEG |

Depodaki `config/media-slots.php` bugün slot başına düz bir genişlik
listesi tutuyor (`renditions: [320, 640, …]`). Kaynak ise türevleri
ADLANDIRIYOR ve her birine bir İŞ veriyor. Fark önemli: `320` bir sayıdır,
`small · menü kartı · telefon` bir karardır — ve kural değiştiğinde hangi
ekranın etkileneceğini yalnız ikincisi söyler.

### 6.2 Desteklenen türler

| Aile | Azami | Uzantılar | Not |
| --- | --- | --- | --- |
| Görseller | 25 MB | jpg, png, heic, heif, webp, avif, tiff, svg | HEIC/HEIF telefonda JPEG'e çevrilir; AVIF ve WebP olduğu gibi alınır |
| Video | 200 MB | mp4, webm, mov, m4v | MOV/MP4 sunucuda WebM'e çevrilebilir; ilk kare kapak olur |
| Belgeler | 25 MB | pdf, csv, xlsx, docx | PDF panelde sayfa sayfa okunur; ilk sayfa kapak görseli olur |
| Ses | 50 MB | — | — |

DİKKAT — depo bugün SVG'yi REDDEDİYOR (`config/media-slots.php`: "SVG yok:
sanitize eden katman olmadan kabul edilmez", `docs/49` Faz 2 madde 6).
Kaynak SVG'yi listede gösteriyor. Bu bir ÇELİŞKİDİR ve sahibin kuralı
gereği kaynak kazanır — ama SVG'yi kabul etmek, önce bir temizleyici
katman yazmayı gerektirir. Temizleyici olmadan kabul edilmemeli;
"kaynak öyle diyor" bir güvenlik açığını haklı çıkarmaz. Karar sahibe
sorulacak bir üründür, sessizce açılacak bir kapı değil.

### 6.3 Dönüştürme hedefleri

AVIF (~%74 küçük, en küçük) · WebP (~%58, en geniş destek) ·
WebM (~%62, video VP9/AV1) · JPEG (~%40, her yerde açılan yedek).
**Asıl korunur; dönüşen dosya yeni sürüm olur.**

### 6.4 Kota kartları ve "yeri ne dolduruyor"

Kartlar: Depolama, Dosya sayısı, Dönüştürme, CDN trafiği — her biri
kullanılan/sınır, yüzde ve bir not taşır; sınıra yakınken not uyarı
rengine döner. Kırılım: Ürünler, Kampanyalar, Video, Belgeler, **Çöp**
(çöp uyarı renginde, çünkü boşaltılabilir bir yer kaplar).

### 6.5 Ayarlar — desen alanları

- **Dizin yapısı:** yıl/ay · tek klasör · klasör ağacı
- **Dosya adı:** slug + karma · yalnız slug · özgün ad
- **Tarih biçimi:** 4 Eylül 2026 · 2026-09-04 · göreli — hepsi
  `Europe/Istanbul`

### 6.6 Güvenlik anahtarları

| Anahtar | Ne yapar | Önerilen |
| --- | --- | --- |
| Virüs taraması | Temiz çıkmayan dosya karantinaya alınır | evet |
| İçerik imzası kontrolü | Uzantıya güvenilmez; "resim.jpg" aslında betikse reddedilir | evet |
| Gömülü veriyi temizle | Konum, cihaz ve seri numarası silinir | evet |
| Özel dosyalarda imzalı bağlantı | Belgeler süresi dolan adresle açılır | hayır |
| Filigran | Paylaşım boyutuna küçük logo basılır | hayır |

İlk üçü depoda ZATEN var (tarama, MIME doğrulama, EXIF temizleme) ama
kullanıcıya gösterilmiyor ve kapatılamıyor. Anahtar yapmak, kapatılabilir
yapmak demektir: **"virüs taraması" anahtarı kapatılabilir OLMAMALI** —
kapatılabilir bir güvenlik anahtarı, kapatıldığı gün bir güvenlik açığıdır.
Ekranda durumu GÖSTERİLİR, kapatılamaz.

### 6.7 Olgunluk seviyeleri

L0 Yok (henüz yapılmadı) · L1 Çalışıyor (mutlu yol tamam) ·
L2 Güvenli (hata ve kısıt durumları var) · L3 Ölçülüyor (sayı ve kanıt
üretiyor) · L4 Olgun (kendini onarıyor, kullanıcıya anlatıyor).
