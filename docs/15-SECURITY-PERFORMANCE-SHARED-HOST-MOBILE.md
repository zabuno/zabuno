# 15 — Security, Performance, Shared-Host & Mobile

**PLANNING ONLY.**

## 1. Kimlik ve oturum güvenliği

**Fortify headless + Sanctum first-party session cookie** (`docs/05` §3'ün
teknik detayı burada operasyonelleştirilir): CSRF koruması, e-posta doğrulama,
opsiyonel OTP/passkey roadmap'i (MVP dışı).

**Brute-force / credential-stuffing koruması** katmanlı:
1. Rate limiting (IP + hesap bazlı, ayrı ayrı).
2. Breached-password kontrolü (bilinen sızıntı listeleriyle karşılaştırma).
3. Generic hata mesajları ("hesap yok" ile "şifre yanlış" ayırt edilemez —
   enumeration önleme).
4. Session/device risk skorlama (alışılmadık cihaz/konum sinyali).
5. Lockout-abuse önleme: bir saldırganın *başkasının* hesabını art arda yanlış
   denemeyle kilitleyip DoS yapmasını engelleyen üst sınır (agresif lockout'un
   kendisi bir saldırı vektörüne dönüşmesin).

## 2. Native app vs. Cloudflare Enterprise dürüstlüğü

Bu platformun native (self-hosted) katmanı **Cloudflare Enterprise'ın volumetric
DDoS gücünü eşleyemez** — bu açıkça kabul edilir, gizlenmez. Native L7 kontroller
(rate limit, queue, caching, request cap, circuit breaker, abuse detection,
origin allowlist) **tamamlayıcıdır**, yerine geçmez. Upstream bir CDN/WAF/DDoS
katmanı (Cloudflare veya benzeri) **zorunluluk** olarak yazılır, opsiyonel süs
değildir (`docs/28` kaynak kaydı).

## 3. Cache hiyerarşisi

```
browser → CDN → PHP/opcache → Laravel response/data cache → DB
```

**DB cache varsayılandır** (shared-host uyumu); file/array cache doğru bağlamda
(örn. tek-worker senaryosu) kullanılır; **Redis opsiyoneldir**. Tag-based
invalidation, tenant-scoped cache key'leri, cache stampede önleme, stale-while-
revalidate deseni planlanır.

## 4. Shared-host performans bütçesi ve kapasite matrisi

Prebuilt (önceden derlenmiş) frontend asset'leri, queue fallback (worker yoksa
senkron/cron-tetikli işleme), cron scheduler, observability (log/health), backup/
restore, **RPO/RTO** (Recovery Point/Time Objective) hedefleri tanımlanır.

**Kapasite matrisi** (host'a göre değişen yetenekler, `docs/07` §8 ile çapraz
bağlı): Imagick, ffmpeg, `exec`/`proc_open`, symlink oluşturma, uzun süreli
worker process. Her özellik bu yeteneklerin **yokluğunda zarif biçimde düşer**
(graceful degradation) — hard-fail kullanıcı deneyimini kesmez.

### 4a. Matrisi kim doldurur (2026-08-26'da uygulandı)

Bu matris artık tahminle değil ölçümle doldurulur:

```bash
php artisan platform:evidence:host-capability
```

Komut çalıştığı host'a Imagick/GD/ffmpeg/Redis/SQLite varlığını, `exec` ve
symlink izinlerini, bellek/yükleme/zaman aşımı sınırlarını sorar; sonucu
`host_capability_evidence` tablosuna bir satır olarak yazar ve devreye giren
düşüş planını adıyla basar. Prob **salt-okunurdur** ve host'ta iz bırakmaz.

Eksik bir yetenek komutu başarısız ETMEZ — bu bilinçlidir. Paylaşımlı
barındırmada eksik yetenek normaldir; hard-fail, her deploy'u kırar ve ekibi
kontrolü tamamen kapatmaya iter. Bunun yerine her eksik yetenek, restoran
sahibinin yaşayacağı sonuçla birlikte yazılır: "Imagick yok" değil, "görsel
türevleri GD ile üretilir, kalite biraz düşer, akış çalışır".

Kanıt: `tests/Feature/Platform/HostCapabilityProbeTest.php`
(MED-01-PROBE-01…MED-01-EVIDENCE-05).

### 4b. Hedef barındırma kümesi (owner kararı, 2026-08-27)

Hedef tek bir sağlayıcı değil, **beşi birden**: netcup (AMD EPYC), Hetzner,
Turhost paylaşımlı, Natro paylaşımlı, Güzel Hosting paylaşımlı. Karar
"hepsinde **kalıcı** olarak çalışsın" biçimindedir.

Bu, mimari için tek yönlü ve bağlayıcı bir kısıttır: **en dar ortam taban
kabul edilir.** Bir özellik yalnız kök erişimi olan bir sunucuda çalışıyorsa,
o özellik bu ürün için yoktur.

| Yetenek | Paylaşımlı barındırmada | Ürünün cevabı |
| --- | --- | --- |
| Uzun süreli worker | Genelde yok | Kuyruk cron ile işlenir; senkron düşüş yolu korunur |
| Redis | Genelde yok | Önbellek ve kuyruk veritabanı sürücüsü |
| `exec`/`proc_open` | Sağlayıcıya göre değişir | ClamAV çağrılamazsa yükleme karantinada kalır (fail-closed) |
| Imagick | Genelde yok | Görsel türevleri GD ile |
| `nginx.conf` erişimi | Yok | URL normalizasyonu **uygulama katmanında** (`docs/38` §8) |
| symlink | Değişken | `storage:link` yerine kopyalama/alternatif servis yolu |
| Yükleme sınırı | Düşük olabilir (ölçülen: 2M) | Sınır ölçülür ve kullanıcıya söylenir |
| Sertifika yönetimi | Panel üzerinden | Otomasyon varsayılmaz |

Ölçüm tahminle değil komutla yapılır:

```bash
php artisan platform:evidence:host-capability
```

Sağlayıcı seçildiğinde aynı komut orada çalıştırılır ve çıktısı
`host_capability_evidence` tablosuna yazılır; iki kayıt yan yana konarak
sağlayıcılar karşılaştırılır. Bu belge o ölçümün yerine geçmez.

### Kapı, belge değil

Bu sözleşme `tests/Feature/Portability/SharedHostContractTest.php` ile
zorlanır. Bugün her şey uyumlu; kapının amacı yarın eklenen bir satırın bunu
sessizce bozmasını engellemektir — çünkü kırılma yerel makinede değil,
müşterinin sunucusunda görünür.

| Kural | Neyi engeller |
| --- | --- |
| `HOST-EXT-01` | Paylaşımlı barındırmada bulunmayan bir eklentiyi zorunlu kılmak (`composer install` orada hiç çalışmaz) |
| `HOST-DRIVER-02` | Dağıtım örneklerinde Redis/Memcached seçmek |
| `HOST-PROCESS-03` | `exec`/`proc_open` çağrısını planlı düşüş yolu olmadan yaymak |
| `HOST-QUEUE-04` | Kuyruğa iş atıp onu cron ile işleyecek yol bırakmamak |
| `HOST-SYMLINK-05` | Çalışma zamanında symlink oluşturmaya bağımlı olmak |
| `HOST-WEBSERVER-06` | URL kuralını `nginx.conf`'a taşımak; oraya erişimin yok |

`HOST-QUEUE-04` özellikle önemlidir: paylaşımlı barındırmada sürekli çalışan
bir worker yoktur. Kuyruğa atılan bir iş, onu işleyecek zamanlanmış bir komut
olmadan veritabanında **sonsuza kadar bekler** — hata vermez, sadece hiç
olmaz.

> **GÜNCELLEME (2026-09-05, FF-161):** burada bir zamanlar "bugün kuyruğa
> hiçbir iş atılmıyor; kapı o günü bekliyor" yazıyordu. O gün geldi:
> fotoğraftan menü aktarımı kuyruğa iş bırakıyor
> (`app/Jobs/ExtractMenuBatchPageJob`) ve tüketen süreç de kuruldu —
> `routes/console.php` dakikada bir `queue:work --stop-when-empty`,
> zamanlayıcı `docker/supervisord.conf` altında. `HOST-QUEUE-04` karşılandı.

**Bu kararın bedeli açıkça yazılır:** paylaşımlı barındırma tabanı, gerçek
zamanlı işleme, ağır görsel işleme ve yüksek eşzamanlılık gerektiren
özellikleri Stage 1 kapsamı dışında tutar. Bu bir eksiklik değil, seçilen
taşınabilirliğin fiyatıdır.

## 5. Mobil strateji

- **Diner (müşteri) tarafı**: zero-install web/PWA — native app **gerekmez**.
- **Restoran personeli tarafı**: ileride (Growth stage) **Capacitor native shell**
  değerlendirilir. **Apple onayı garanti edilemez** — bu bir varsayım değil,
  App Store review sürecinin doğasıdır, açıkça yazılır.
- Native shell'in **substantive value** (gerçek native gerektiren) senaryoları:
  push notification, kamera ile QR test, offline draft/sync, share sheet, deep
  link, secure storage. Bu değer olmadan native shell **gerekçesizdir** — yalnız
  "native olsun" diye üretilmez.
- App Store / Play review policy checklist'i `docs/28`'de kaynak olarak
  kayıtlıdır.

## 5a. PWA baseline kontratı (diner tarafı) ve admin offline sınırı

Diner (public menü) tarafının zero-install PWA'sı aşağıdaki baseline'ı
**eksiksiz** taşır — bu bir "ileride eklenir" özelliği değil, `docs/26` §1
Stage 1/2 dağılımında izlenen bir kontrattır:

- **Web app manifest** (isim, icon set, theme/background color, display mode)
  ve **installability** (tarayıcının "add to home screen" kriterlerini
  karşılama).
- **Service worker scope**: **yalnız public diner menü/QR resolve yüzeyini**
  kapsar. Restoran admin paneli ve ödeme akışları service worker cache
  kapsamı **dışındadır** — bu yüzeyler için service worker **kayıtlı
  değildir**, bir cache-first/stale-while-revalidate stratejisi
  uygulanmaz (karışık cache riski yapısal olarak imkânsız kılınır, "dikkatli
  kullanılır" değil).
- **Versioned cache + update prompt**: her yayın (publication) yeni bir cache
  versiyonu üretir; kullanıcıya "yeni sürüm var, yenile" **görünür ve erişilebilir**
  bir bildirim gösterilir — sessiz/otomatik zorla yenileme yoktur.
- **Offline fallback (salt-okunur)**: bağlantı kesildiğinde son başarıyla
  önbelleğe alınmış yayın snapshot'ı **salt-okunur** gösterilir (`docs/04`
  Publication ile tutarlı — "son başarılı sürümü koru" ilkesi burada da
  geçerlidir), boş sayfa/hata ekranı **değil**. Diner tarafında bir "içerik
  düzenleme draft'ı" kavramı **yoktur** — diner (müşteri) zaten içerik
  düzenlemez, yalnız görüntüler; bu yüzden burada korunacak bir düzenleme
  taslağı da yoktur.
- **Bounded offline analytics queue**: bağlantı yokken yalnız **idempotent ve
  zararsız** analytics event'leri (örn. bekleyen bir görüntüleme/scan
  ping'i) yerelde kuyruğa alınır ve bağlantı gelince gönderilir; bu kuyruk
  süresiz/sınırsız değildir ve hiçbir mutasyon (veri değiştirme) içermez —
  yalnız salt-gözlemsel event gönderimi.
- **Deterministic cache invalidation**: fingerprint/versiyon tabanlıdır
  (`docs/07` §2 ile aynı felsefe) — stale cache'in ne zaman geçersiz olacağı
  tahmin edilebilir, "bazen çalışır bazen çalışmaz" davranışı yasaktır.
- **Erişilebilir install/update UI**: WCAG 2.2 AA (`docs/06` §8).

### Admin/staff tarafı — non-authoritative local form-draft recovery (PWA değil)

Admin/staff paneli service worker kapsamı dışında olduğundan burada bir
"offline PWA deneyimi" **yoktur**. Bir form doldururken bağlantı koparsa,
tarayıcı yalnız **yerel, non-authoritative bir form-draft recovery**
sağlayabilir (örn. sayfa-yerel `localStorage`/form state — bir service
worker cache mekanizması **değildir** ve otomatik bir background mutation
**tetiklemez**). Bu recovery:

- Yalnız **güvenli form girdisini** (örn. henüz gönderilmemiş bir ürün
  açıklaması metni) kapsar; **hiçbir koşulda** ödeme credential'ı, secret,
  API key veya izin/rol (permission/role) değişikliği yerelde saklanmaz.
- Bağlantı geri geldiğinde form **otomatik gönderilmez** — kullanıcı
  değişikliği gözden geçirir, gerekiyorsa oturum/yetki bağlamını **yeniden
  doğrular** ve **açıkça submit eder**. Sessiz/otomatik arka plan gönderimi
  yoktur.

**Kesin yasak — offline kritik işlem**: offline ödeme, offline yayınlama
(publish), offline izin/rol (permission/role) değişikliği ve herhangi bir
kritik finansal mutasyon **hiçbir koşulda offline çalıştırılmaz ve otomatik
kuyruğa da alınmaz** — ne diner tarafındaki bounded analytics queue'da, ne
admin tarafındaki form-draft recovery'de. "Draft korunur" ifadesi **yalnız**
yukarıdaki güvenli form-girdisi recovery'si için geçerlidir; bir kritik komut
(ödeme/yayınlama/izin/finansal mutasyon) için **hiçbir draft/kuyruk/senkron
mekanizması yoktur** — kullanıcı yalnızca bağlantı geldiğinde işlemi baştan,
bilinçli olarak başlatır.

**Stage dağılımı**: Diner PWA baseline'ın manifest/service-worker/offline-
fallback (salt-okunur) + bounded analytics queue çekirdeği **Stage 1
MVP**'de hazırdır (dinamik QR'ın "aynı QR, güncel menü" vaadiyle tutarlı);
versioned cache/update-prompt olgunlaştırması ve admin-tarafı non-
authoritative form-draft recovery UX'i **Stage 2 Post-MVP**'de tamamlanır
(`docs/26` §1). Growth stage'deki Capacitor native shell bu PWA baseline'ının
**yerine geçmez** — yalnız ölçülmüş bir substantive-value ihtiyacı varsa
(§5 listesi) değerlendirilir ve App Store/Play onayı **garanti edilmez**.

## 6. Standartlar

- **OWASP ASVS 5** (Application Security Verification Standard).
- **WCAG 2.2**, hedef en az **AA**, kritik akışlarda **AAA** aday (`docs/06` §8).
- **NIST SSDF** (Secure Software Development Framework).
- **SBOM** (Software Bill of Materials) + lisans + dependency pinning.
- **OpenTelemetry** (observability).

## 7. Kanonik sahiplik

Güvenlik/performans/shared-host/mobil stratejisinin tek kanonik sahibi burasıdır.
Modül-özel güvenlik kontrolleri (örn. medya upload güvenliği) ilgili modül
spec'inde bu dokümana **link verir**, tekrar etmez.
