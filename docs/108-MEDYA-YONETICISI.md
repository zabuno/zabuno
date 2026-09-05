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

**SVG — sahibin kararı (2026-09-05): AÇILDI.**

Depo SVG'yi reddediyordu (`docs/49` Faz 2 madde 6: sanitize eden katman
yok). Sahibe açıkça soruldu ve "şimdi aç" dendi.

Karar, temizleyiciyle BİRLİKTE uygulandı ve bu bir yavaşlatma değil: SVG
bir görsel değil bir BELGEDİR; içine `<script>`, `onload=`, `javascript:`
bağlantısı, `<foreignObject>` ve harici `<use>` gömülebilir. Menü sayfaları
herkese açık olduğu için temizleyicisiz kabul, misafirin telefonunda
çalışacak kod yüklemeye izin vermek olurdu (stored XSS). Sahip gecikme
istemedi; temizleyici aynı pakette yazıldığı için gecikme olmadı.

Temizleme başarısız olursa dosya REDDEDİLİR (fail-closed) — depodaki tarama
kuralıyla aynı yön.

**PDF — sahibin kararı (2026-09-05): AÇILDI, denetçiyle birlikte.**

Depoda PDF OKUYUCU zaten yazılmış ve testlenmişti (`MediaViewerRegion`,
`ShowMediaViewerController`, `ServeMediaPreviewController`,
`MediaPreviewPolicy`) — ama alım kapısı PDF'i kabul etmediği için ölü koddu:
restoran sahibi alerjen tablosunu panele hiç koyamıyordu. Sahibe açıkça
soruldu; "PDF açılsın — temizleyiciyle birlikte, aynı pakette" dendi. SVG'de
uygulanan yöntemin aynısı uygulandı.

| Karar | Ne yapıldı | Neden |
| --- | --- | --- |
| Slot | Yeni `document` slotu (`formats: [pdf]`, türev yok) | En yakın aday `menuImportSource` bir BESLEME kaynağıdır: yüklenen dosya AI menü içe aktarmaya girer ve orası raster bekler. PDF'i oraya koymak, içe aktarmanın okuyamayacağı bir kaynak yaratırdı |
| Kapı | MIME **ve** ilk bayt (`%PDF-`), ikisi birden | Uzantı da istemcinin bildirdiği tür de yükleyenin denetimindedir |
| Karar biçimi | Saldırı bulunan gövde **reddedilir** | `MaliciousIntakeGateTest`in sözü: fixture hiç saklanmadan 422. Sessizce temizleyip kabul etmek saldırıyı arşivlemek olurdu |
| Ad | `PdfInspector` (temizleyici değil, **denetçi**) | PDF nesneleri çapraz referans tablosunda BAYT KONUMLARIYLA adreslenir; bir nesneyi çıkarmak dosyayı bozar. Bu sınıf hiçbir baytı değiştirmez |
| Türev | **Yok** | imagick yok, GD PDF okumaz. Uydurma kapak çizmek yerine "önizleme yok" denir; okuma yolu "Görüntüle"dir ve orada ASIL `inline` servis edilir |
| Boru hattı | Türevsiz PDF `ready` olur, `failed` OLMAZ | "Türev yok" ile "işlenemedi" aynı şey değildir; ikincisi sahibe belgesinin bozuk olduğunu söylerdi |

Reddedilen yapılar: açılışta/olayla çalışan betik (`/JavaScript`, `/JS`),
eylem (`/AA`, `/OpenAction` — hedef dizisi ve belge içi `/GoTo` HARİÇ),
`/Launch`, `/SubmitForm`, `/ImportData`, `/GoToR`, gömülü dosya
(`/EmbeddedFile`, `/Filespec`), gömülü medya (`/RichMedia`, `/Movie`,
`/Sound`). Ayrıca şifreli (`/Encrypt`), yarım inmiş ve PDF olmayan gövde de
reddedilir — okunamayan dosya "temiz" değildir.

**`/ObjStm` (sıkıştırılmış nesne akışı) — dürüstlük kararı.** PDF 1.5'ten
beri nesne sözlükleri, yani eylemlerin yaşadığı yer, sıkıştırılabilir; ham
baytlarda `/JavaScript` aramak orada hiçbir şey bulmaz. Denetçi bu akışları
AÇAR ve içini aynı kurallarla tarar; açamadığı bir nesne akışı varsa dosyayı
REDDEDER. Göremediğimiz bir şeyi "temiz" diye geçirmiyoruz. Akış gövdeleri
(çizim akışları) taramanın dışındadır: "/Launch nedir?" cümlesini ÇİZEN bir
eğitim notu saldırı değildir.

**Kalan sınır (dürüstlük notu).** Sunucu kapısı açıktır ve testlidir; panelin
YÜKLEME SİHİRBAZI hâlâ görsele göre kuruludur (`accept="image/*"`, ölçü
okuma, kırpma, istemcide küçültme). Bu yüzden `document` slotu bilerek
`/api/media/slot-policies` listesinde GÖSTERİLMEZ: dolduramayacağımız bir
yeri açılır kutuda teklif etmek bir söz ihlalidir. Belgeyi panelden yükleme
yolu ayrı bir pakettir.

**"Aslını sakla" anahtarı — sahibin kararı (2026-09-05): YAPILMADI.**
Asıl KOŞULSUZ korunur; bunun bir ayarı yoktur. Kapatılabilir bir "aslı
sakla" anahtarı, kapatıldığı gün geri dönülemez bir veri kaybıdır ve "dosya
değişmedi" iddiasının tek kanıtı olan parmak izini de anlamsızlaştırırdı —
virüs taraması anahtarıyla aynı gerekçe (§6.6). Ayarlar ekranında anahtar
değil, bir BİLGİ SATIRI vardır.

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
kullanıcıya gösterilmiyor.

**Sahibin kararı (2026-09-05): virüs taraması GÖSTERİLİR, KAPATILAMAZ.**
Kapatılabilir bir güvenlik anahtarı, kapatıldığı gün bir güvenlik açığıdır.
Ayarlar ekranında durumu okunur (açık / bu ortamda çalışmıyor) ama
kullanıcı onu kapatamaz. Kaynağın diğer dört anahtarı kaynağın dediği gibi
açılıp kapanabilir.

### 6.7 Olgunluk seviyeleri

L0 Yok (henüz yapılmadı) · L1 Çalışıyor (mutlu yol tamam) ·
L2 Güvenli (hata ve kısıt durumları var) · L3 Ölçülüyor (sayı ve kanıt
üretiyor) · L4 Olgun (kendini onarıyor, kullanıcıya anlatıyor).
