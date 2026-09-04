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
