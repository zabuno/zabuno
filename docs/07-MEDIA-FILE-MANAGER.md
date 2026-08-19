# 07 — Media & File Manager

**PLANNING ONLY.** Hiçbir kütüphane burada "kurulu" değildir; hepsi adaydır.

## 1. Teknoloji adayları (koşullu, plan aşamasında)

| İhtiyaç | Aday | Sınıf |
|---|---|---|
| Upload UI + görsel editör | Uppy React/headless + Image Editor | koşullu |
| Medya kütüphanesi çekirdeği | Spatie Media Library | kanıtlanmış (yaygın Laravel ekosistem paketi) |
| Görsel işleme | Intervention Image | kanıtlanmış |
| Depolama soyutlama | Flysystem — local **default**, S3 **opsiyonel** | kanıtlanmış (Flysystem), local-default kararı proje kararı |
| Optimizasyon | Spatie Image Optimizer | koşullu (host'ta binary'lerin var olması şart, bkz. `docs/15` kapasite matrisi) |
| Quarantine/malware-scan adaptörü | Local scanner binary (örn. ClamAV) **veya** harici tarama servisi — hangisi seçileceği kapasite/maliyet/gizlilik değerlendirmesine bağlı | koşullu — deneysel (henüz seçilmedi, `docs/16` MED-03) |

Bu altısı "kurulu" değildir — hiçbiri Composer'a eklenmemiş veya sunucuya
kurulmamıştır (quarantine/malware-scan adaptörü bir Composer paketi olmayabilir,
sistem binary'si veya harici servis de olabilir; sınıf tanımı bu farkı
kapsar). `docs/03` ADR disiplinine göre her biri seçim anında bir
compatibility spike ile doğrulanmadan sürüm pinlenmez.

## 2. Immutable original + derivative fingerprint modeli

- Yüklenen orijinal dosya **immutable** ve **private**'tır — mantıksal olarak
  ayrı bir **private namespace**'te tutulur; hiçbir orijinal doğrudan public
  URL ile servis edilmez. Bu ayrım **mantıksal**dır (erişim/görünürlük
  sözleşmesi); gerçek fiziksel dizin/disk yolu adı bu külliyatın hard-code
  kanonu **değildir** — storage adaptörü (Flysystem disk konfigürasyonu)
  implementasyon zamanında seçilir.
- **Türevler blanket-public değildir.** Türevler kendi ayrı bir
  **derivative/delivery namespace**'inde yaşar ve bu namespace'in görünürlüğü
  **kaynağın (source asset'in) mevcut yayın durumundan inherit edilir**
  (kalıtımlı görünürlük politikası — sabit "her türev public'tir" kuralı
  **değildir**):
  - Bir **draft/private** asset'in (henüz yayınlanmamış menü/ürün görseli,
    onboarding taslağı vb.) türevleri **private/signed**'dır — yalnız
    yetkili tenant oturumu, kısa ömürlü **signed URL** ile erişebilir.
  - Yalnız **published** bir public slot'a (yayınlanmış menü/kurumsal
    sayfa/QR print vb.) bağlı derivative **public** olur; bu geçiş,
    kaynağın publish event'iyle **tetiklenir** (`docs/04` Publication ile
    tutarlı) — bir asset "yükleniyor" veya "draft" durumundayken türevi asla
    baştan public üretilmez.
  - Bu, CORE-13'ün **tenant authorization** kontrolüyle (`modules/
    core-file-media.md` §Permissions) birlikte çalışır: signed URL süresi
    dolduğunda veya tenant/scope uyuşmazlığında erişim reddedilir.
- Her türev (derivative — resize/crop/format dönüşümü) bir **fingerprint** ile
  anahtarlanır:

```
fingerprint = hash(source_hash + crop/focal/safe_zone_params + output_recipe + engine_version)
```

- Yalnız eşleşen fingerprint mevcutsa türev **yeniden üretilmez** (idempotence).
  `engine_version` alanı, işleme motoru güncellendiğinde eski türevlerin sessizce
  "yanlış ama cache'li" kalmasını önler — versiyon değişince fingerprint değişir,
  yeniden üretim tetiklenir. Her türev kaydı, üretildiği anki **optimize edilmiş
  metadata**'sını (boyut, format, kalite/sıkıştırma parametreleri, fingerprint
  girdileri) saklar — bu metadata, aynı girdi için tekrar optimize/encode
  işlemi çalıştırmayı **engelleyen** kontrol noktasıdır (yalnız fingerprint
  eşleşmesi değil, metadata bütünlüğü de doğrulanır).

## 3. Recipe kavramı (hard-code değil, tema-tanımlı)

Görsel slot recipe'leri (hangi boyut/format bir yuvaya gider) **tema geliştirme
sırasında** tanımlanır; 320/640/1280/2000 gibi sayılar bu külliyatın hard-code
kanonu **değildir** — örnek değerlerdir. Her tema kendi recipe setini
`docs/06` tema domenleriyle uyumlu şekilde bildirir.

## 4. Format politikası

- **Still image**: optimize edildikten sonra **WebP zorunlu türev**; **AVIF
  opsiyonel + fallback**.
- **Video**: **WebM** yalnız video için; **MP4 fallback**. ("Görseli WebM'e
  çevir" gibi bir istek/hata **anlamsızdır** — WebM bir video formatıdır, still
  image formatı değildir; bu külliyat bu hatayı bilinçli olarak düzeltir ve
  hiçbir dokümanda "görsel → WebM" ifadesi kullanmaz.)

## 5. Güvenlik ve gizlilik pipeline'ı — fail-closed intake sırası

**Kritik sıralama kuralı**: hiçbir güvensiz/tam decode veya optimizasyon adımı,
malware/security-scan **geçmeden** çalışmaz. Sıra tam olarak şudur:

```
1. quarantine intake  — bytes önce private, non-executable, tenant-scoped
   quarantine storage'a yazılır. Public URL YOK, hiçbir published slot'a
   bağlı DEĞİL. Bu adım MIME/uzantıya güvenmez, hiçbir decode/parse
   yapmaz — yalnız byte'ları güvenli biçimde durable olarak yazar.
2. intake-validation  — ucuz, bounded, tam decode GEREKTİRMEYEN kontroller:
   authorization/quota kontrolü, byte-size sınırı, magic-byte/MIME imza
   tespiti (dosyanın başlık byte'larını okur, uzantıya güvenmez — bu bir
   header sniff'tir, bir codec'in tam görüntüyü parse etmesi DEĞİLDİR),
   mümkünse güvenli header/preflight sınırları (örn. dosya formatı
   header'ından deklare edilen boyut alanının, tam raster decode
   yapılmadan ön-elemesi). Bu adımlardan biri reddederse asset
   `rejected` durumuna geçer, quarantine'den asla çıkmaz.
3. security-scan (malware scan)  — asset hâlâ quarantine'deyken bir
   malware-scan adaptörü (koşullu aday, `docs/16` MED-03) çalıştırılır.
4. [yalnız scan geçerse] AssetSecurityScanPassed / AssetAccepted event'i
   — bounded, sandboxed/resource-limited decode'u BAŞLATAN tek tetikleyici
   budur (bkz. `modules/core-file-media.md` §Public contracts/events,
   §ECA hooks). Bu noktadan **önce** hiçbir decode/crop/optimize/encode
   adımı çalışmaz.
5. bounded/sandboxed decode  — decompression bomb kontrolü (dekode edilmiş
   piksel boyutu/karmaşıklık sınırı, dosya boyutundan bağımsız,
   kaynak-sınırlı bir ortamda) BU adımın içindedir, decode'dan **önce**
   veya decode ile **eş zamanlı** uygulanır — decode'un kendisi zaten
   sandboxed/resource-limited'dır.
6. orient (EXIF orientation) + GPS/EXIF strip (gizlilik) + ICC profil
   politikası (`docs/16`'da açık madde).
7. crop → optimize → encode.
8. release — yalnız bu adımdan sonra asset §2'deki kalıtımlı görünürlük
   politikasına göre bir public/published slot'a bağlanabilir.
```

**Önemli açıklık**: malware scan'in geçmesi, decode'un kendisinin
**risksiz** olduğu anlamına **gelmez** — decode adımı (5) scan'den sonra
bile her zaman **bounded/sandboxed/resource-limited** çalıştırılır
(decompression bomb kontrolü dahil); "scan geçti, artık güvenle her şeyi
decode edebiliriz" varsayımı bu külliyatta **reddedilir**.

- MIME/magic-byte doğrulama (uzantıya güvenilmez) + boyut sınırı — adım 2'nin
  parçasıdır, decode'dan önce çalışır.
- **SVG özel yolu**: SVG dosyaları önce (2)-(3) adımlarından (validation +
  malware scan) geçer, sonra release'den önce **strict sanitize**
  (script/foreignObject temizliği — XSS vektörü, `docs/15` güvenlik kontrol
  listesiyle çapraz bağlı) uygulanır; sanitize adımı (6)-(7) aşamasının SVG'ye
  özgü karşılığıdır — scan tek başına SVG'yi güvenli hâle getirmez, sanitize
  hâlâ zorunludur.
- **Fail-closed scanner davranışı**: malware-scan adaptörü **eksik,
  kullanılamaz, timeout veya belirsiz (indeterminate)** sonuç döndürürse,
  asset **quarantine'de kalır** — hiçbir decode/processing/derivative/
  `ready`/`release` adımı çalışmaz. **Shared-host'ta scanner yoksa**
  (kapasite matrisi negatif, `docs/15` §4), asset yine **quarantine'de
  kalır** — bu, §8'deki "zarif degradation" kuralının bir **istisnasıdır**:
  graceful degradation burada kaliteyi düşürerek devam etmek anlamına
  **gelmez** (güvenliği düşürmek yasaktır); onun yerine **safe quarantine**
  (dosya private/quarantined kalır, kullanıcıya "tarama bekleniyor/
  kullanılamıyor" açıkça gösterilir) + manuel review veya harici scanner
  rotasına yönlendirme devreye girer. Red (reject) sonucu da mümkündür, ama
  **asla** güvenlik-atlama (bypass) yoluyla bir "geçti" sonucuna
  düşürülmez.

## 6. Kurumsal/restoran/QR/e-posta medya slotları (envanter)

| Alan | Slotlar |
|---|---|
| Kurumsal site | hero, cards, pricing, features, testimonial, avatar |
| Restoran | logo, cover, favicon, OG image, app icon, profile/avatar (external/social paylaşım), external/social share görselleri (provider registry alan sahipliği `modules/core-tenancy.md` §Business profile contract'ta; bu satır yalnız asset slot/recipe'ini sahiplenir) |
| Menü | category hero |
| Ürün | list/card/detail item, gallery |
| QR | print logo |
| E-posta | header/splash/push görselleri |

Her slot, DPR (device pixel ratio), cihaz/tarayıcı yeteneği, art direction
(farklı crop farklı en-boy oranında), focal point/safe zone ve responsive
`srcset`/`<picture>` çıktısını destekleyecek şekilde tanımlanır. Bu politika
değerlerinin (hangi DPR eşiği, hangi tarayıcı yetenek seti, hangi crop oranı)
kendisi de **tema/context/device/browser'a göre hard-code edilmez** — §3'teki
"recipe hard-code değil, tema-tanımlı" ilkesiyle aynı disiplin burada da
geçerlidir; recipe/policy değerleri tema geliştirme sırasında bildirilir.

## 7. UX katmanı

One-click preset uygulama, bulk upload, progress/retry/resume, before/after
önizleme, asset kullanım grafiği (bu görsel nerelerde kullanılıyor — silme
öncesi kırık referans önleme), replace-without-broken-reference (bir asset
değiştirildiğinde onu kullanan tüm yerler otomatik güncellenir, link kırılmaz),
rights/expiry/alt-text alanları (lisans/erişilebilirlik metadata'sı).

## 8. Kapasite bağımlılığı

Imagick/ffmpeg gibi native binary'lerin yokluğunda pipeline **zarif biçimde
düşer** (`docs/15` §Shared-Host Capability Matrix, `skills/shared-host-capability`)
— hata fırlatıp kullanıcıyı engellemek yerine, desteklenen en yakın format/boyuta
düşer ve durumu loglar. **İstisna**: §5'teki quarantine/security-scan adımı bu
kuralın dışındadır — scanner kapasitesinin yokluğunda pipeline "taramadan
devam et" yönünde düşmez (bu güvenliği azaltır), yalnız §5'te tanımlanan safe
quarantine rotasına düşer.

## 9. Kanonik sahiplik

Media pipeline mimarisi, format politikası ve fingerprint modeli burada
kanoniktir. Upstream araştırma provenance'ı `research/upstream/imageoptimization/UPSTREAM.md`'de,
kavram-eşleme detayı orada özetlenmiştir — bu dosya o kavramları PHP/Laravel
planına **çevirir**, kod portlamaz.
