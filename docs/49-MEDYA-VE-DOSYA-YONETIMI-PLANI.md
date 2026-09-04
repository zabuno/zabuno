# 49 — Medya ve Dosya Yönetimi: fazlanmış geliştirme planı

**Durum:** Faz 1 ✅ (2026-08-27). Faz 2-7'nin BAZI maddeleri başka paketlerde kapandı — aşağıda her fazın başında yazılı. Kalan program `docs/98` FF-68…FF-71.
**Requirement ID:** `DAM-v1`
**İlgili:** `docs/38` (URL politikası), `docs/44`, `docs/46` (ölçüm),
`docs/47` (form standardı), `docs/48` (320px-first),
`~/DEV/zabuno/imageoptimization-main` (dış şartname)

---

## 0. Modülün doğru adı

Bu bir "dosya yükleyici" değildir:

> **Zabuno Digital Asset Management & Delivery**
> File Storage Core + Media Library + Processing Pipeline + Publication
> Delivery + Governance

---

## 1. Bugün ne var — ölçüldü

```sql
media_assets(
  id, workspace_id, disk_path, original_name, mime_type,
  size_bytes, alt_text, slot, status, timestamps, deleted_at
)
```

**Tek düz tablo, tek `status` sütunu.** Araştırma yönergesinin "yetersiz"
dediği şeyin tam karşılığı. Bugün olmayanlar:

| Yok | Sonucu |
| --- | --- |
| Asset ≠ fiziksel dosya ayrımı | Aynı görselin ikinci sürümü yok; düzenleme aslını bozar |
| Version / Rendition | Responsive görsel üretilemez; `srcset` yazılamaz |
| Usage (kullanım grafiği) | "Nerede kullanılıyor?" cevapsız; silme kör |
| Publication snapshot bağı | Fotoğrafı düzenlemek CANLI menüyü habersiz değiştirir |
| Checksum | Bozulma ve yinelenen dosya görünmez |
| Upload session | Kesilen yükleme baştan başlar |
| Collection / tag / arama | Kütüphane 200 dosyada kullanılamaz olur |
| Kota | Maliyet ölçülemez |
| Immutable URL | CDN eski görseli göstermeye devam eder |

İşleme tarafında `ext-gd` var; `vips`/`imagick` yok.

---

## 2. `imageoptimization-main`'den ne alınacak

O klasör **15 faz, 102 görevlik** bir medya motoru şartnamesi. Frappe + Vue 3
için yazılmış; **alan bilgisi yığın-bağımsız ve doğrudan kullanılabilir.**

### 2.1 Alınacaklar

| Ne | Neden |
| --- | --- |
| **INV-01..INV-07 değişmezleri** | Upscale yasağı, DPI≠piksel, fayda kuralı, alfa korunur, idempotency. Bunlar tartışılmaz kurallar ve doğrudan bizim test adlarımız olur |
| **İşleme boru hattı** | `PROBE → GUARD → DECODE → ORIENT → COLOR → DPI → PIXEL CAP → CLASSIFY → MASTER → CROP → RESIZE → ENCODE → BENEFIT → LQIP → REPORT` — sıra gerekçeli ve doğru |
| **Slot kataloğu disiplini** | Her slot bir politikaya bağlanır; **belirlenmemiş slot BLOKEDİR** ve tahminle doldurulamaz. Sahibinin çalışma biçimiyle birebir aynı |
| **Veri modeli** | Asset / Source / Version / Rendition / Crop Intent / Profile / Policy / Processing Job / Usage / Quality Report |
| **Crop & preview simülatörü** | 13 cihaz × 5 yerleşim, odak noktası, `srcset` seçim göstergesi. Kavram ve referans uygulama olarak alınır |
| **Golden fixture korpusu** | `fixtures/malicious/` — decode edilmeden reddedilmesi gereken dosyalar. Güvenlik testinin tabanı |
| **Kabul kriterleri biçimi** | Her görevde ölçülebilir kriter (örn. "30 MB dosyada probe < 50 ms") |

### 2.2 Alınmayacaklar — ve neden

| Ne | Neden alınmıyor | Bizdeki karşılığı |
| --- | --- | --- |
| **pyvips** (Python) | Zabuno PHP/Laravel | `ext-vips` (jcupitt/php-vips) — aynı libvips, PHP bağlaması. Yoksa `ext-imagick`. **`ext-gd` yetmez**: sequential access ve ICC yönetimi yok |
| **Frappe DocType** | ORM ve migration modeli farklı | Laravel migration + repository port |
| **Vue 3 headless** | Bizde React 19 | Aynı ekranlar React'te |
| **Frappe queue** | | Laravel queue + `media_processing_jobs` |

### 2.3 Fiziksel olarak ne kopyalanacak

```
imageoptimization-main/docs/30-faz2-medya-standartlari.html   → slot katalogu
imageoptimization-main/docs/41-faz4-veri-modeli.html          → veri modeli
imageoptimization-main/docs/50-faz6-image-engine.html         → boru hattı + INV
imageoptimization-main/docs/51-faz7-video-engine.html         → video profilleri
imageoptimization-main/docs/61-faz10-crop-studio.html         → crop arayüzü
imageoptimization-main/docs/62-faz11-simulator.html           → simülatör
imageoptimization-main/gorseller/*                            → test fixture'ları
```

Bunlar `docs/design-corpus/` yanına **kaynak olarak** alınır; kararlar bu
belgeye özetlenir (külliyat için `docs/36`'da uygulanan yöntemin aynısı).

---

## 3. Altı alt domain

| Alt domain | Sorumluluk |
| --- | --- |
| File Storage Core | Binary, object storage, checksum, şifreleme, versiyon |
| Upload & Ingestion | Yükleme oturumu, doğrulama, karantina, virüs taraması |
| Media Asset Management | Metadata, koleksiyon, etiket, arama, kullanım ilişkileri |
| Media Processing | Crop, resize, format dönüşümü, rendition üretimi |
| Media Delivery | Public/private URL, signed URL, cache, CDN |
| Governance & Operations | Tenant izolasyonu, kota, audit, retention, backup |

Tek modüler monolit modülü; **kod ve veri modeli seviyesinde ayrı.**

---

## 4. Üç ayrı durum ekseni

Bugünkü tek `status` sütunu üçe ayrılır. Tek alana doldurmak, ilerideki her
sorguyu belirsiz yapar:

```
processing_status : PENDING UPLOADING UPLOADED QUARANTINED SCANNING
                    PROCESSING READY FAILED REJECTED
lifecycle_status  : DRAFT ACTIVE ARCHIVED TRASHED PURGED
visibility        : PRIVATE TENANT PUBLIC SIGNED
```

---

## 5. URL politikası ve SEO ile bağ (`docs/38`, `docs/46`)

### 5.1 Değişmez (immutable) adres

```
YANLIŞ  /media/products/menemen.webp
DOĞRU   /media/{asset_id}/{version_id}/product-640.webp
```

Yeni sürüm yeni adres üretir. Sonucu: CDN eski dosyayı göstermez, purge
zorunluluğu kalkar, eski yayın snapshot'ı çalışmaya devam eder, rollback
güvenlidir.

### 5.2 Depolama anahtarı ile görünen ad ayrı

```
storage_key      tenants/{uuid}/assets/{uuid}/original   ← ASLA değişmez
original_filename IMG_8734.JPG
display_name      Kaşarlı Menemen                        ← değişebilir
```

Restoran adı veya koleksiyon değişince tek bayt taşınmaz.

### 5.3 SEO: alt metin varlıkta DEĞİL, KULLANIMDA

Aynı fotoğraf menü ürününde içerik görseli, ana sayfada dekoratif görsel
olabilir. W3C alternatif metnin **kullanım amacına** göre belirlenmesini
ister:

```
media_asset_translations(asset_id, locale, alt_text, title, caption)
media_usages(..., locale, alt_text_override)
```

Fragment kullanılmaz, adres dile göre değişmez (`docs/38`).

### 5.4 Responsive teslim

`320w 480w 640w 960w 1280w 1600w` rendition seti; frontend `srcset`,
`sizes`, `<picture>`, `width`/`height` (CLS), `loading`, LQIP. 320w bilerek
ilk sıradadır (`docs/48`).

---

## 6. AI-first konumu

AI **öneri üretir, yayınlamaz.** Kritik yolculuk AI kapalıyken tam çalışır
(`docs/36` §5.7).

| Yetenek | AI'ın işi | Sağlayıcı | Kullanıcının işi |
| --- | --- | --- | --- |
| Alt metin | Görselden taslak metin önerir | Yerel | Görür, düzenler, onaylar |
| Smart crop | Odak noktası önerir | Yerel (CV) | Kabul veya elle taşır |
| Etiketleme | Etiket önerir | Yerel | Onaylar |
| Yinelenen | Benzer görselleri gömme ile işaretler | Yerel | Hangisi kalacağına karar verir |
| Kalite | "Bu görsel menüde bulanık görünecek" | Yerel (CV) | Yeniden yükler ya da yok sayar |
| **Görselden menü çıkarma** | Fotoğraf/PDF/grafikten yapılandırılmış menü; belirsiz alanları İŞARETLER | **Gemini** → OpenAI | Belirsizleri doğrular, onaylar |

Sağlayıcı sırası ve gerekçesi: `docs/51` §3.3. Kısaca: **yerel → Gemini →
OpenAI → Claude**; medya işlerinin çoğu yerel modelle yapılabilir ve
yapılmalıdır — hem bedava hem veri sunucudan çıkmaz.

Her AI önerisi `docs/47` Kural 10'a uyar: kaynak, etkilenen kayıt, önizleme,
onay, geri alma, denetim kaydı. **Otomatik yayın yok.**

---

## 7. Yükleme arayüzü — kütüphane kararı

| Aday | Artı | Eksi | Karar |
| --- | --- | --- | --- |
| **react-dropzone** | Kancaya dayalı, bağımlılıksız, sürükle-bırak + pano + dosya seçici, kendi arayüzümüz | Devam ettirilebilir (resumable) yükleme yok; chunk'ı biz yazarız | **Faz 2 seçimi** |
| Uppy (+ tus) | Devam ettirilebilir, duraklat/sürdür, çok kaynak | Ağır, kendi arayüz dili, tus sunucusu gerekir | **Faz 6'da video için yeniden değerlendir** |
| FilePond | Hazır güzel arayüz | Kendi tasarım dilini dayatır — token zincirimizle çatışır | Hayır |

**Gerekçe:** `docs/36` bileşenin ham geometri bilmemesini ve tasarım
kimliğinin bizim olmasını şart koşuyor. Hazır arayüz getiren kütüphaneler bu
kuralla çatışır. `react-dropzone` yalnız DAVRANIŞ verir, görünümü bize
bırakır.

Video/PDF gerçekten geldiğinde kesintili mobil bağlantı için `tus` bir
ihtiyaç hâline gelir; o karar Faz 6'da, veriyle verilir.

---

## 8. Fazlar

### Faz 1 — Mimari temel ✅ **TAMAMLANDI (2026-08-27)**

| # | İş | Durum | Nerede |
| --- | --- | --- | --- |
| 1 | Slot kataloğu ve politikaları | ✅ | `config/media-slots.php` (17 slot), `app/Domain/Media/SlotPolicy.php` |
| 2 | Üç durum ekseni | ✅ | `ProcessingStatus`, `LifecycleStatus`, `Visibility` |
| 3 | Veri modeli | ✅ | `media_blobs`, `media_versions`, `media_renditions`, `media_usages`, `media_processing_jobs` + `media_assets` üç eksene taşındı |
| 4 | Immutable URL şeması | ✅ | `docs/38` §4b |
| 5 | `vips` kararı | ✅ | `docker/Dockerfile`. `composer.json`'a HENÜZ eklenmedi: motor yazılmadı ve kapı kapsama arıyor, eşitlik değil |
| 6 | INV-01..07 test adı olarak | ✅ | `tests/Unit/Media/MediaInvariantsTest.php` (9 test) |

**`media_policies` tablosu bilerek YOK.** Politikalar tenant başına
değişmiyor: ürünün kendi kuralları. Yapılandırmada dururlar ve sürüm
kontrolündedirler; veritabanına taşımak, aynı kuralın iki yerde yaşamasına
ve bir gün ayrışmasına yol açardı. Tenant başına politika gerçekten
gerekirse tablo o zaman gelir.

### Faz 2 — Güvenli alım (ingestion)

**Durum (FF-68, 2026-09-04):** 1 ✅ idempotency anahtarı (`X-Idempotency-Key`,
tenant başına tekil; yeniden deneme ikinci görsel yaratmaz) — `media_upload_sessions`
tablosu BİLEREK yok: tus/resumable olmadan bir oturum tablosu tiyatro olurdu,
§7 kararı gereği resumable Faz 6'da video ile gelir. 2 🔶 ilerleme çubuğu +
aynı anahtarla yeniden deneme + istemci tarafı boyut/piksel ön kontrolü ✅;
**çoklu dosya FF-70'e** (kütüphane yeniden tasarımıyla — tek alt metinli
çoklu yükleme, alt metni işlevsiz kılardı). 3 ✅ magic-byte + **piksel tavanı
decode edilmeden** (`max_megapixels` config'de vardı, alımda UYGULANMIYORDU —
gerçek boşluk) + bayt sınırı config'den (sabit 50 MB ile config'in 30 MB'ı
çelişiyordu). 4 ✅ zincir. 5 ✅ (`ServeRendition` yalnız rendition; asıl
karantinada).

6 ⚠️ **DEĞİŞTİ — sahibin kararı, 2026-09-05: SVG AÇILDI** (`docs/108` §6.2).
2026-09-04'te buraya "SVG alımda reddedilir ve slot politikalarından
çıkarıldı" yazılmıştı; o cümlenin gerekçesi (sanitize eden katman yok)
kalkmadı, KARŞILANDI. Sahibe açıkça soruldu, "şimdi aç" dedi ve kabul
temizleyiciyle AYNI pakette açıldı: `App\Domain\Media\SvgSanitizer` —
allowlist tabanlı, saf, bağımlılıksız. Betik, olay özniteliği (`on*`),
`#` dışına çıkan `href`/`xlink:href`, `<foreignObject>`, harici `<use>`,
`<animate>`/`<set>`, `@import`/dış `url(…)` ve XML varlığı (XXE, milyar
gülüş) taşıyan gövde **reddedilir** (fail-closed) — temizlenip kabul
EDİLMEZ. SVG yalnız vektör slotlarında serbesttir (`logo`, `printLogo`,
`favicon`); `itemImage` gibi fotoğraf slotları SVG'yi hâlâ reddeder.
Temizlenmiş gövde asla aslın üzerine yazılmaz: asıl karantinada
değişmeden durur (Faz 3), temizlenmiş hâl `vector` profilli bir
**rendition** olarak saklanır ve halka giden tek şey odur. Türevler
`ServeRenditionController` üzerinden `default-src 'none'; sandbox` CSP'si
ile verilir — temizleyicide bir gün boşluk çıksa bile tarayıcı o adreste
betik çalıştırmaz.

Kabul ölçütü ✅: `tests/fixtures/malicious/*` (PHP-jpg, HTML-png, betikli
SVG, `onload`lu SVG, harici `use`, `foreignObject`, XXE, 100000×100000 PNG
başlığı) `MaliciousIntakeGateTest` ile CI kapısı; ayrıca
`SvgIntakeAndDeliveryTest` aynı SVG fixture'larını SVG'nin SERBEST olduğu
slotta da reddettirir (slot kuralı arkasına saklanmasınlar diye) ve
`SvgSanitizerTest` temizleyiciyi HTTP olmadan saldırı gövdeleriyle sınar.
Polyglot GIF+PHP kabul edilir ama gövdesi hiçbir rendition'a sızmaz (test).

1. `media_upload_sessions` + idempotency key
2. `react-dropzone` ile sürükle-bırak, pano, çoklu seçim, ilerleme
3. Sunucu tarafı doğrulama: uzantı allowlist + iddia edilen MIME + **magic
   bytes** + decoder ile gerçekten açılabilme + boyut + piksel sınırı
4. Karantina → tarama → metadata temizleme → yeniden encode
5. `READY` olmadan public URL üretilmez
6. SVG **temizlenmeden** servis edilmez (asıl hiç servis edilmez; halka
   giden yalnız temizlenmiş türevdir, o da CSP'li)

**Kabul:** `fixtures/malicious/` içindeki her dosya **decode edilmeden**
reddedilir. Bu testler CI kapısıdır.

### Faz 3 — Asset / Version / Rendition

**Durum (FF-69, 2026-09-04):** 1 ✅ asıl karantinada değişmez ve parmak izi
(`original_checksum_sha256`) alım anında alınır — "değişmedi" iddiasının tek
kanıtı. 2 🔶 sürüm append-only: yeniden üretim v2 açar, **geri alma v3 açar**
(geçmiş yeniden yazılmaz; yayın snapshot'ı hâlâ eski sürümü gösterir) —
**crop/resize düzenleyicisi Faz 8'de**, o yüzden "non-destructive edit"in
bugünkü iki biçimi yeniden üretim ve geri alma. 3 ✅. 4 ✅ aynı parmak izi
ikinci kez gelince `duplicateOfId` — kiracı İÇİNDE, komşu bilmez. 5 ✅
`media:reprocess --workspace=` + `POST .../reprocess`; başarısız yeniden
üretim varlığı `failed` yapmaz, `ready` kalır ve sebep iş kaydında.
Kabul: `MediaVersioningTest` (7). Kütüphane ekranı bunların hiçbirini henüz
göstermiyor → FF-70.

1. Original **immutable**
2. Crop/resize **non-destructive**; her düzenleme yeni version
3. Rendition seti `320..1600w`; pipeline sürümü kaydedilir
4. SHA-256 checksum; aynı tenant içinde yinelenen tespiti
5. Toplu yeniden üretim (reprocess)

**Kabul:** INV-01..07 yeşil; rollback çalışır.

### Faz 4 — Kütüphane arayüzü

**Durum (FF-70, 2026-09-04):** 1 ✅ liste/ızgara, küçük resim (en küçük hazır
rendition), arama (alt metin + dosya adı), slot/durum süzgeci; 2 kısmen ✅
"kullanılmayanlar" akıllı süzgeci var, koleksiyon/etiket ⬜ (FF-71'e
ertelendi — kota ve izin matrisiyle birlikte); 3 ✅ detay çekmecesi:
önizleme, metadata (dosya, boyut, tarih, slot, kopya uyarısı), kullanım,
sürümler (geri al), "boyutları yeniden üret" — Haklar/Etkinlik ⬜ (Faz 7);
4 ✅ catalog bileşenleri (`TextInput`/`Select`/`Checkbox`/`Tabs`/`DrawerPanel`/
`ConfirmDialog`), piksel/breakpoint sınıfı yok (MediaPage kapısı).
**FF-76 (2026-09-04, sahibin "burada değişen bir şey yok" ekranı):** sayfa iki
sütun (solda "Add photos", sağda "Your photos"); çoklu dosya sürükle-bırak
(kalan dosyalar dosya adından türeyen adla listelenir, sırayla yüklenir);
çekmecede ad (alt metin) düzeltme (`PATCH media/{id}`, depolama anahtarı
değişmez); slot envanteri ve yaşam döngüsü "How photos are handled" altında
katlanır; boş kütüphane yol tarifi verir.

1. Grid/liste, önizleme, arama, filtre
2. Koleksiyon + etiket + akıllı koleksiyon ("alt metni eksik", "kullanılmayan")
3. Asset detayı: Önizleme / Metadata / Kullanım / Sürümler / Rendition /
   Haklar / Etkinlik / Teknik ayrıntı
4. Formlar `docs/47`, düzen `docs/48`

**Kabul:** 320×480'de kullanılabilir; teknik kelime kullanıcı metninde yok.

### Faz 5 — Kullanım grafiği ve yayın bağı

**Durum (FF-70, 2026-09-04):** 1 ✅ (`media_usages` entity/slot/publication;
`GET media/{id}/usages` insan adıyla — "Adana Kebap", "#7" değil); 2 ✅ silme
etki önizlemesi: kullanılmayan görsel doğrudan çöpe, kullanılan görselde
diyalog "nerede kullanılıyor" + "bağı kes ve çöpe at" / vazgeç; yayındaki
menüde olan görselde düğme kapalı ve sebep yazılı (sunucudaki 409 kullanıcıya
yaşatılmaz); "değiştir" (yerine başka görsel seç) ⬜ → FF-71; 3 ✅ silme çöpe
atar (dosya diskte kalır, `lifecycle_status=trashed`), Çöp sekmesi + geri al,
`media:purge-trash --days` (varsayılan `config/media-slots.php`
`trash_retention_days=30`; yayında kullanılan asla purge edilmez;
dosya silinemezse satır da kalır); 4 ✅ (`recordPublicationUsages` version'ı
dondurur, `docs/77`); 5 ⬜ fallback zinciri → FF-71 (yer tutucu görsel
politikası kota/izinle birlikte).

1. `media_usages`: entity, slot, location, locale, publication, override
2. Silmeden önce **etki önizlemesi**: değiştir / bağı kes / vazgeç
3. Trash → retention → purge
4. **Publication snapshot asset VERSION'a bağlanır** — fotoğrafı düzenlemek
   canlı menüyü kendiliğinden değiştirmez
5. Fallback zinciri: ürün → kategori → tenant → sistem

**Kabul:** Kullanılan bir asset doğrudan silinemez.

### Faz 6 — Teslim ve CDN

**Durum (FF-71, 2026-09-04):** 1 ✅ immutable URL + `Cache-Control` +
`ETag`/`If-None-Match` → 304 (ikinci açılışta sıfır bayt); 2 ✅ asıl özel
(karantina diski, adres yok), rendition açık; asıl indirme 10 dakikalık
İMZALI adres (`POST media/{id}/download-link` → `GET
/media/original/{ws}/{id}?signature=…`, `no-store`, `attachment`); 3 ⬜ (CDN
yok — netcup yerel disk kararı; önbellek anahtarında kiracı kimliği CDN
gelince); 4 ✅ `srcset` + `width`/`height` + LQIP (16 px JPEG data URI,
`media_versions.lqip`, misafir menüsünde arka plan olarak); 5 ⬜ video/PDF
Faz 2'ye bağlı (sahip kararı: video sonra).

1. Immutable URL, `Cache-Control`, `ETag`
2. Private original / public rendition ayrımı, signed URL
3. Tenant kimliği cache anahtarında
4. `srcset`/`sizes`/`<picture>`, LQIP, `width`/`height`
5. Video/PDF kapsamı ve `tus` kararı

**Kabul:** QR menüde LCP ölçülür ve iyileşir.

### Faz 7 — Yönetişim

**Durum (FF-71, 2026-09-04):** 1 ✅ plan → kota (`config/media-quota.php`:
`starter`=Free, `restaurant`=Standart, `team`=Pro; aboneliksiz → `starter`);
asıl bayt + görsel sayısı + aylık yükleme + çöp süresi; rendition kota dışı,
çöp kota içi (`docs/98` §7); egress/dönüşüm sayısı ⬜ (ölçüm altyapısı yok);
2 ✅ dolunca yalnız yükleme 422 ile durur, sebep sahibin cümlesi; teslim
kapıdan geçmez; `GET media/quota` sayaçları ekranda; 3 ✅ `media.manage`
(Owner/Manager/Editor) ve `media.download_original` (her rol — sahip kararı
"tamamen serbest", izin yine ayrı); Member yükleyemez/silemez; 4 ✅ (FF-97)
denetim izi: `media_audits` append-only tablosu — yükleme, ad değişikliği,
çöpe atma, geri alma, yeniden üretme, sürüme dönme ve asıl-dosya indirme
isteği; fail e-postasıyla; varlık silinse de kayıt yaşar; Media sayfasında
kapalı bir bölüm olarak okunur (`GET media/audits`, `media.manage` ister);
5 ✅ `media:reconcile
[--fix]`: kırık kayıt (satır var, dosya yok) ve yetim dosya raporu; `--fix`
yetimi siler, kırığı `failed`'a çeker, satır silmez.

1. Tenant kotası: original, rendition, asset sayısı, aylık upload, egress,
   dönüşüm sayısı, trash
2. **Kota dolunca canlı menü teslimi KESİLMEZ**; upload ve yeni processing durur
3. İzin matrisi — `media.download_original` ayrı bir izindir
4. Audit log
5. Reconciliation: veritabanında olup storage'da olmayan, tersi

**Kabul:** Restore testi geçer — "yedek alındı" değil, "geri yüklendi ve
referanslar çalıştı".

### Faz 8 — Crop stüdyosu ve simülatör
1. Odak noktası, safe area, aspect preset, art direction
2. 13 cihaz × yerleşim simülatörü — hangi rendition seçiliyor gösterilir
3. Auto-orientation, ICC, sRGB, şeffaflık, watermark

### Faz 9 — AI önerileri
1. Alt metin önerisi (öneri → onay)
2. Smart crop
3. Etiket, yakın-yinelenen, kalite uyarısı
4. Hepsi `docs/47` Kural 10 anatomisiyle

### Faz 10+ — İleri
OCR, içerik moderasyonu, video transcoding, harici DAM entegrasyonu, cold
archive.

---

## 9. Dosya yöneticisi (görsel olmayan)

`asset_kind` **hiçbir zaman image'a sabitlenmez.**

| Tür | Faz | Özel gereksinim |
| --- | --- | --- |
| PDF | 6 | Sayfa sayısı, ilk sayfa önizleme, sanitize/CDR, aktif içerik yok |
| DOCX/XLSX | 7 | Önizleme yok, yalnız indirme; makro içeren dosya karantinada |
| Video | 6 | Süre, codec, poster, transcoding, `tus` |
| Ses | 10 | Süre, waveform |
| Üretilen | 5 | QR SVG/PNG, PDF menü, sosyal görsel — `source_type=generated`, `generator_version` |

---

## 10. n8n ve OpenClaw — KAPSAM DIŞI

Sahibinin kararı: **medya için n8n ve OpenClaw iptal.**

Upload, tarama, dönüşüm, yetkilendirme ve yayın boru hattının tamamı çekirdek
uygulamada, Laravel queue/event altyapısıyla çalışır. Gerekçe zaten
teknikti: işlemsel bütünlük, retry, güvenlik ve düşük gecikme. Bu kararla
birlikte medya tarafında dış otomasyon **hiçbir aşamada** yoktur.

---

## 11. Sahibinin kararı gereken noktalar

**Beşi de 2026-08-27'de sahibi tarafından karara bağlandı.**

| # | Karar | Sahibinin cevabı | Sonucu |
| --- | --- | --- | --- |
| 1 | Slot kataloğu | "Zaten mevcut, panelde açılır menüde geliyor. Ama o sayfa çok yetersiz ve UX felsefesine düşman." | Slot LİSTESİ sabit (17 slot). Minimum ölçüler ve politikalar **türetildi** (§12) — sahibinin verdiği bir sayı değil, benim önerim; değiştirilebilir |
| 2 | Depolama | **netcup yerel disk** | S3 yok. `media_blobs` yine de sağlayıcıdan bağımsız kalır: disk değişirse şema değişmez |
| 3 | Video | **Faz 2'ye bağla** | `tus` ve transcoding Faz 2 kapsamında; `asset_kind` baştan video'yu tanır |
| 4 | Tenant kotası | **"Sen belirle"** | §13'te önerildi |
| 5 | Original indirme | **Tamamen serbest** | `media.download_original` ayrı izin OLMAYACAK. Araştırma yönergesi bunu ayırmayı öneriyordu; sahibi aksine karar verdi ve karar sahibinindir |

### Kararların planı nasıl değiştirdiği

- **Faz 1 açıldı** — beklenen karar kalmadı.
- **Faz 6'daki "video/PDF kapsamı ve `tus` kararı" Faz 2'ye taşındı.**
- **Faz 7'deki izin matrisinden `media.download_original` çıkarıldı.**
- S3'e özgü işler (bucket versioning, cross-region replica) **kapsam dışı**;
  yerine disk üzerinde checksum tabanlı reconciliation ve dosya sistemi
  yedeği gelir.

---

## 12. Olgunluk cetveli — sahibin sorduğu başlıklar nerede? (2026-09-04)

Sahip: "file manager, media library, size optimization, media file SEO/ASEO,
re-naming, enumeration security… onlar nerede, dokümanlarda var mı?" Her
başlığın **bu belgede** karşılığı, bugünkü seviyesi ve kodu:

| Başlık | Nerede | Bugün | Kod / kanıt |
| --- | --- | --- | --- |
| **Media management (kütüphane)** | §7, Faz 4-5 | **L2** — liste/ızgara, arama, süzgeç, detay, kullanım, sürüm, çöp/geri al | `MediaLibraryRegion`, `MediaAssetDetailDrawer`, `MediaLibraryTrashAndUsagesTest` |
| **File management (görsel olmayan)** | §9, Faz 6-7 | **L0** — veri modeli `asset_kind` serbest; PDF/DOCX/XLSX alımı KAPALI (sahip kararı: video/PDF Faz 2 programına) | `config/media-slots.php` yalnız görsel MIME; `MaliciousIntakeGateTest` |
| **Size optimization** | §5.4, Faz 3 | **L3** — slot politikasına göre `{w}w` rendition seti, upscale yasağı, WebP, LQIP; istemci ön-kontrol (boyut/piksel) | `GdMediaAssetProcessor`, `MediaUploadRegion` precheck, `MediaRenditionPipelineTest` |
| **Media file SEO / alt metin** | §5.3 | **L2** — alt metin yüklemede zorunlu, kullanımda `alt_text_override`, misafir menüsünde `srcset`+`width/height`+LQIP; çok dilli `media_asset_translations` ⬜ (Faz 8+) | `EloquentMenuMedia::imageForVersion`, `public-menu.blade.php` |
| **Re-naming** | §5.2 | **L2** — `storage_key` değişmez, `original_name` ve `display_name` ayrı sütunlar; ekranda ad = alt metin; görünen adı ayrı düzenleme ⬜ (Faz 8 detay formu) | `media_assets.display_name`, `MediaAssetDetailDrawer` |
| **Enumeration security** | §5.1, `docs/38` §4b | **L3** — rendition adresi `/media/r/{id}-{32 hex parmak izi}.{fmt}`: kimlik sayılarak taranamaz, yanlış parmak izi 404; asıl dosyanın herkese açık adresi YOK, yalnız 10 dk imzalı bağlantı; kiracı dışı varlık 404 | `RenditionUrl::matches` (sabit süreli), `ServeOriginalController` (`signed`), `MediaDeliveryAndGovernanceTest` |
| **Dropzone / yükleme deneyimi** | Faz 2 | **L2** — sürükle-bırak, ilerleme çubuğu (XHR), yeniden deneme, idempotency, zararlı dosya kapısı; çoklu dosya ve `tus` ⬜ (Faz 2 programı, video ile) | `MediaDropzone`, `MediaUploadRegion`, `MediaIntakeTest` |
| **Kota / yönetişim** | §7 karar, Faz 7 | **L2** — plan bazlı kota, çöp süresi, izin matrisi, uzlaştırma; audit log ⬜ | `config/media-quota.php`, `media:reconcile` |

Seviye tanımı: **L0** veri modeli var, ürün yok · **L1** tek yol çalışır ·
**L2** günlük iş tam, ileri araçlar eksik · **L3** ölçülmüş ve kapılı ·
**L4** otomatik/AI destekli. Sahibin "yapmamışsın" dediği başlıkların hiçbiri
L0'da değil; L0 olan tek şey görsel olmayan dosya alımıdır ve o, sahibin
kendi "video/PDF Faz 2'ye" kararıyla oradadır.
