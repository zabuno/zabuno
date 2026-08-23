# 33 — S1-WP02A: Identity & Sessions Delivery Contract (CORE-01 dikey dilimi)

**DURUM: WP02A local-candidate-targeted-green; public-promotion RED.** Bu
dosya artık yalnız docs-only bir kapsam sözleşmesi değildir — S1-WP02'nin
CORE-01-only alt dilimi (**S1-WP02A**: register→verification-pending→
signed/expiring email verification→authenticated cookie session→logout)
**yerel olarak implement edilmiş ve çalıştırılabilir haldedir** (bkz. §Final
durum aşağıda, `http://127.0.0.1:8787/register`,
`http://127.0.0.1:8787/login`), hedefli kanıtla desteklenir. Bu dosya
kapsam/mimari-sınır/threat-model/kabul sözleşmesini de taşımaya devam eder;
§Final durum bölümü mevcut yerel gerçeği kaydeder. **Public Git promotion**
ise ayrı ve hâlâ **RED**'dir — S1-WP01'in aynı kalan iki P1 owner-kararı
blocker'ı yüzünden (composer license/legal owner kararı; managed
`AGENTS.md` yetki bloğu ile `docs/31` public-safe yasağı arasındaki
çelişki, `docs/27` §6, `README.md` §Çalıştırma). Bu iki blocker bir yerel
runtime hatası **değildir**, owner/yönetişim kararı bekler. Bu paket
kapsamında Git mutasyonu (add/commit/push) **yapılmadı** (`AGENTS.md` §6).

## 0. Bu dosyanın konumu ve neden yeni dosya (`AGENTS.md` §2)

`docs/18` (Stage 1 anlatısı) ve `docs/26` §3 (WP outcome/scope/predecessor)
CORE-01'i yalnız **özet** seviyede taşır ("Identity/Tenant/Permission/Admin
shell" tek WP satırı, `docs/26` S1-WP02). Hiçbiri WP-seviyesinde bir threat
model, blind RED test aday matrisi veya Fortify/Sanctum sürüm-kontratı kanıtı
taşımaz — bu ayrıntı seviyesi mevcut 33 dosyadan hiçbirinin bounded context'ine
girmez (`docs/26` §7: dosyalar "ne biter"i taşır, uygulama ayrıntısını
taşımaz). Bu yüzden yeni bir kanonik dosya açılmıştır (`AGENTS.md` §2 —
genişletme değerlendirmesi burada kayıtlıdır). `modules/core-identity-sessions.md`
CORE-01'in modül-spec sahibi olmaya devam eder; bu dosya onu **tekrar
tanımlamaz**, yalnız WP02A'nın dar dikey dilimi için delivery-contract ekler.

## 1. Owner özeti

- **once**: `docs/26` S1-WP02 satırı "not-started"; CORE-01'in nasıl teslim
  edileceğine dair WP-seviyesinde bir sözleşme yoktu — yalnız modül-spec
  (`modules/core-identity-sessions.md`) ve stage-özeti (`docs/18`) vardı;
  dokümantasyon WP02A'yı docs-only/not-started/blocked olarak tanımlıyordu.
- **simdi**: CORE-01'in dar bir dikey dilimi (**register → verification-pending
  → signed/expiring/single-use email verification → authenticated cookie
  session → logout**) için kapsam, mimari sınır, threat model ve blind RED
  test aday matrisi bu dosyada tanımlıdır **ve** bu dilim yerel olarak
  implement edilmiştir — hedefli kanıtla desteklenen bir çalıştırılabilir
  implementation candidate'tır (bkz. §Final durum).
- **fark**: Bir kullanıcı İngilizce varsayılan arayüzde
  (`http://127.0.0.1:8787/register`) kayıt olabiliyor, doğrulama-bekliyor
  ekranını görüyor, imzalı/süreli/tek-kullanımlık e-posta linkine tıklayıp
  authenticated cookie session'a geçiyor ve çıkış yapabiliyor —
  (`http://127.0.0.1:8787/login`) — CORE-02 tenancy/workspace olmadan,
  yalnız CORE-01 kimlik katmanı; bu, yerel hedefli kanıtla doğrulanmış bir
  gerçek, ama **public promotion RED** kaldığı sürece yalnız yerel bir
  candidate'tır.
- **kullaniciYolculugu** (somut CRM-benzeri analoji): Bir kullanıcının bir form
  doldurduğu (kayıt), formun "onay bekliyor" durumuna girdiği (verification-
  pending — CRM'deki "submit → pending review" ile eşdeğer), bağımsız bir
  kanaldan (e-posta) gelen imzalı bir linkle onayın tamamlandığı (verified —
  "approved") ve bunun sonucu olarak oturumun açıldığı bir döngü; "reddetme/
  retry" karşılığı burada süresi dolmuş veya tekrar kullanılan bir linkin
  **veri kaybı olmadan** güvenli bir hata/no-op durumuna düşmesidir (§6
  VERIFY-02/VERIFY-03). Bu döngü artık yerel ortamda hedefli testlerle
  gösterilmiştir; tarayıcıda gerçek bir hesap oluşturma bu turda
  **yapılmadı** — bu otomatik-test kanıtıdır, manuel E2E iddiası değildir.
- **kalanEngel**: (1) S1-WP01 **public-promotion**'ının hâlâ RED olması (iki
  P1 owner-kararı açık: composer license/legal owner kararı; managed
  `AGENTS.md`/`docs/31` public-governance çelişkisi) — bu, WP02A'nın yerel
  çalışırlığını **engellemez** ama public Git yayınını engeller; (2) S1-WP02'nin
  geri kalanı (CORE-02 Tenancy, CORE-03 Authorization, CORE-06 Settings/
  Secrets, admin panel iskeleti) hâlâ not-started'tır; (3) CI/full QA turu
  ve WP02A'nın kendi Exit Gate'i (§12) hâlâ tamamlanmamıştır. Bu üç madde
  bir yerel runtime hatası **değildir** — owner/yönetişim kararı ve sonraki
  iş paketleri bekler.
- **capability_delta**: ürün kabiliyeti **0 → bounded CORE-01 local
  candidate** (register→verification-pending→email verification→
  authenticated session→logout, yerel hedefli kanıtla). Bu, restoran
  işletme kabiliyeti değildir. Ana ilerleme sayacı bu delta'dan **ayrı ve
  bağımsız olarak 0/8**'de kalır (Stage 1 Exit Gate'i etkilenmedi,
  `docs/17` §4) — WP02A'nın kendi bounded kapsamındaki bu delta ana sayacı
  **artırmaz**; WP02A'nın kendi kapanış raporuna (§12 Exit gate GREEN
  aldığında) ayrıca işlenir.
- **şu-an-çalıştırılabilir/çalıştırılamaz iddiası**: **Yerel olarak
  çalıştırılabilir (candidate), public promotion RED.** WP02A'nın register/
  verification/session/logout akışı yerel ortamda hedefli kanıtla
  çalıştığı doğrulanmıştır (§Final durum). S1-WP01A foundation iskeletinin
  kendi çalıştırılabilirlik iddiası (`README.md` §Çalıştırılabilirlik
  iddiası) bu dosyadan **etkilenmez**.

## 2. Predecessor kapısı ve waterfall disiplini

`docs/26` §3: S1-WP02'nin predecessor'ı **S1-WP01**'dir; bu satır WP02A için
de **aynen geçerlidir** (WP02A, WP02'nin bir alt dilimidir, ayrı bir
predecessor zinciri açmaz). S1-WP01'in kendi durumu: `in-progress` (alt paket
S1-WP01A implementation-in-progress), **public-promotion RED** — iki açık P1
owner-kararı blocker'ı (composer strict license metadata/owner kararı +
`AGENTS.md`/`docs/31` public-governance çelişkisi, `docs/27` §6). Bu iki
blocker, S1-WP01A'nın **kendi yerel çalışırlığını** engellemedi (§Çalıştırma,
`README.md`); WP02A'nın yerel implementasyonu da aynı şekilde bu blocker'lar
açıkken **yerel olarak** ilerlemiştir — bu iki blocker yalnız **public Git
promotion/yayın**'ı bloke eder, yerel runtime'ı değil.

- Bu iki blocker kapanıp S1-WP01 **public-promotion GREEN** almadan: hiçbir
  paketin (S1-WP01A, S1-WP02A dahil) public Git yayını **yapılmaz**
  (`docs/17` §1 ilkesinin public-promotion boyutuna uygulanması).
- Ana ilerleme sayacı **0/8**, Stage 1 **1/8 aktif** olarak **değişmeden**
  kalır (`docs/17` §4, `README.md` §İlerleme) — WP02A'nın yerel çalışırlığı
  bir stage'i tamamlamaz, hatta bir WP'yi bile tamamlamaz.

Durum etiketi: **WP02A local-candidate-targeted-green; public-promotion
RED** (bu dosyanın kapsam sözleşmesi + yerel implementasyon kanıtı tamamdır,
ama public Git yayını yukarıdaki iki P1 blocker kapanana kadar bloke kalır).

## 3. Scope / non-goals

**Scope (CORE-01 only, tek dikey dilim)**:

| Adım | İçerik |
|---|---|
| Register | E-posta + şifre ile kayıt formu, İngilizce varsayılan kopya, `pending_verification` hesap durumu (`modules/core-identity-sessions.md` §States) |
| Verification-pending | Kullanıcıya "doğrulama linkine tıkla" bilgilendirme ekranı, linki yeniden gönder aksiyonu (rate-limited) |
| Email verification | İmzalı (`signed` middleware), süreli, tek-kullanımlık-semantikli link; tıklanınca `email_verified_at` set edilir, `Verified` event yayınlanır |
| Authenticated cookie session | Sanctum stateful first-party cookie session (bearer token **değil**); CSRF korumalı |
| Logout | Sunucu-taraflı session invalidation + CSRF token rotasyonu |

**Non-goals (bu WP02A'da yok — açıkça hariç tutulur)**:

- **Tenant/Workspace/Membership** (CORE-02) — kullanıcı hesabı tenant-bağımsız
  kalır (`modules/core-identity-sessions.md` §Tenant isolation ile tutarlı).
- **RBAC/ABAC/ReBAC/Authorization** (CORE-03) — hiçbir yetki/rol kararı bu
  pakette **yok**; register edilen kullanıcı henüz hiçbir workspace'e üye
  değildir.
- **Settings/Secrets (CORE-06), admin panel iskeleti** — `docs/26` S1-WP02
  satırındaki geniş kapsamın geri kalanı, WP02A'ya dahil **değildir**.
- **Invitation / davet kabulü** — `docs/18` scope listesinde var ama WP02A
  yalnız self-register akışını kapsar, davet akışı ayrı bir gelecek dilimdir.
- **Password reset** — CORE-01 modül-spec'inin bir parçası olmasına rağmen bu
  dikey dilimin **dışındadır** (görev talimatı kapsam sınırı); `docs/16`'ya
  ayrı bir açık madde gerekmez, yalnız WP02'nin geri kalan alt dilimlerinden
  biri olarak `docs/26` S1-WP02 satırında kalır.
- **Social login, passkey, SMS doğrulama** — zaten `modules/core-identity-sessions.md`
  §Open questions'ta M1+ kararı olarak işaretli, bu paket bunu **değiştirmez**.
- **Billing/Menu/QR/AI provider çağrısı/Docker/Next.js/Filament** — bu
  külliyatın genelinde zaten yasak/kapsam-dışı (`docs/03` ADR-L06/L08,
  `docs/09`), burada yalnız açıkça teyit edilir.

## 4. Architecture boundary

`docs/03` ADR-L02 ile **birebir aynı** Onion + MVC sınırı, WP02A'ya somut
olarak uygulanır (ADR-L02'yi **yeniden tanımlamaz**, yalnız CORE-01 dikey
dilimine **uygular**):

```
Domain ← Application ← Infrastructure/Adapters ← MVC Delivery
```

- **Domain**: framework-free, strict (`declare(strict_types=1)`, `final`
  sınıflar, value object'ler — ADR-L03). Kullanıcı/oturum aggregate'i,
  `EmailAddress`/`PasswordHash` gibi value object'ler burada yaşar; Eloquent,
  Facade, `Request` nesnesi Domain'e **sızmaz**.
- **Application**: use-case orkestrasyonu (Register, VerifyEmail, Logout) —
  Domain'i çağırır, Infrastructure port'larını (mail gönderimi, hash servisi,
  session servisi) interface üzerinden kullanır.
- **Infrastructure/Adapters**: Fortify/Sanctum adaptörleri, Laravel Mail
  adaptörü, Eloquent persistence adaptörü — Domain'in port'larını **implement
  eder**, Domain onları bilmez.
- **MVC Delivery**: Laravel controller'lar ince kalır (yalnız HTTP→use-case
  adaptasyonu, ADR-L02); **React View-only** — React hiçbir iş kuralı
  taşımaz (kayıt/doğrulama başarı/red kararı her zaman sunucu tarafındadır,
  ADR-L02 "React'te iş kuralı yasak" maddesiyle birebir).
- **Frontend bileşen kütüphanesi**: Flowbite React birincil (ADR-L06);
  shadcn/ui yalnız **source-owned** (npm bağımlılığı değil, kod projeye
  kopyalanır); Radix yalnız Flowbite/shadcn'de eksik erişilebilir primitive
  gerektiğinde **adapter katmanı arkasında** kullanılır — doğrudan Radix
  importu delivery katmanına serpiştirilmez.

## 5. Dikey dilim sırası ve oturum zamanlaması (açık varsayım)

```
Register (pending_verification) → Verification-pending ekranı
  → İmzalı/süreli link tıklanır → email_verified_at set edilir
  → Authenticated cookie session → Logout
```

**Açık varsayım (implementasyon zamanı kararı, bu dosya kilitlemez)**: Fortify'nin
varsayılan `CreateNewUser` action'ı tipik olarak kayıt anında `Auth::login()`
çağırır (oturum kayıtta açılır, `verified` middleware yalnız korumalı
route'ları e-posta doğrulanana kadar bloklar) — bu davranış Fortify'nin genel
bilinen varsayılanıdır, ancak bu oturumda fetch edilen sayfa içeriğinde
birebir bu cümleyle **doğrulanmadı** (bkz. `docs/28` ilgili satır notu). WP02A
implementasyonu başladığında bu zamanlama (kayıtta mı, doğrulamada mı oturum
açılır) **açıkça bir ADR/karar notuyla sabitlenmeli** ve S1WP02A-SESSION-01/02
(§11) bu karardan **bağımsız olarak** her iki durumda da geçerli kalacak
şekilde yazılmıştır (oturumun *ne zaman* açıldığından çok *nasıl* davrandığını
test eder — rotasyon, CSRF, invalidation).

## 6. Server-owned auth threat model

Backend **authoritative**'dir; frontend yalnız affordance sağlar
(`docs/05` §2 ile tutarlı). Aşağıdaki her madde §11'deki bir veya daha fazla
requirement ID'ye bağlıdır.

- **Rate limit**: Register, e-posta doğrulama linki yeniden gönderme, ve
  (varsa) doğrulama handler endpoint'i katmanlı rate limit taşır — IP + hesap
  bazlı ayrı ayrı, lockout-abuse önleme üst sınırıyla (`docs/15` §1
  maddeleriyle birebir, burada tekrar tanımlanmaz, yalnız CORE-01
  register/verify endpoint'lerine **uygulanır**).
- **CSRF**: Sanctum SPA akışı — `/sanctum/csrf-cookie` → `XSRF-TOKEN` cookie →
  `X-XSRF-TOKEN` header round-trip zorunlu; state-changing (register/verify/
  logout) istek bu round-trip olmadan **reddedilir**.
- **Session rotation/fixation**: Kimlik doğrulama durum geçişlerinde
  (register→login, verify) session ID **rotate edilir** — fixation saldırısı
  önlenir; eski session ID'nin yeni durumla geçerli kalması **yasaktır**.
- **Enumeration-safe hatalar**: "hesap yok" ile "şifre yanlış"/"zaten kayıtlı"
  ayırt edilemez — generic mesaj politikası `docs/15` §1 madde 3 ile birebir,
  register'daki duplicate-email durumuna da **uygulanır**.
- **Şifre hash**: Düz metin asla loglanmaz; framework'ün güçlü varsayılan hash
  sürücüsü kullanılır (genel bilgi — Laravel `config/hashing.php` varsayılanı;
  bu oturumda ayrıca fetch edilmedi, `koşullu` sınıfında kalır, implementasyon
  zamanı doğrulanmalı).
- **Duplicate normalized email**: E-posta normalize edilir (case-insensitive +
  trim) **önce** unique kontrolü yapılır; normalize edilmeden yapılan bir
  kontrol aynı adresin farklı case'lerle iki kez kaydına izin verebilir —
  bu açık bir RED test adayıdır (S1WP02A-REG-02).
- **Signed expiry reuse**: İmzalı link süresi dolduğunda güvenli hata (state
  mutasyonu yok); süre dolmadan **tekrar** tıklandığında (zaten doğrulanmış
  kullanıcı) idempotent no-op veya tanımlı güvenli hata — **çift** `Verified`
  event'i veya beklenmeyen oturum yükseltmesi **yasaktır** (S1WP02A-VERIFY-02/03).
- **Logout invalidation**: Logout yalnız istemci cookie'sini silmez —
  sunucu-taraflı session kaydı invalidate edilir (Sanctum session
  guard/store seviyesinde); aynı eski session cookie'siyle yapılan bir
  istek logout **sonrasında** kabul edilmez.
- **Mail fake/local deterministic boundary**: Test/CI/local ortamında
  doğrulama e-postası gerçek SMTP'ye **gitmez** — Laravel `Mail::fake()`/log
  driver ile deterministik yakalanır; shared-host default (ADR-L08) worker
  process varsaymaz, queue senkron/DB-tabanlı fallback ile çalışır
  (`docs/15` §4).
- **WCAG keyboard/form semantics**: Register + verification-pending ekranları
  yalnız klavyeyle tamamlanabilir; label/for eşleşmesi, `aria-invalid`,
  hata-mesajı-input ilişkisi WCAG 2.2 AA (`docs/06` §8, `docs/15` §6,
  `docs/18` §Security/a11y ile tutarlı, burada tekrar tanımlanmaz).
- **English default + translation key policy**: Tüm UI kopyası hardcoded
  literal **değil**, çeviri anahtarı üzerinden gelir (`docs/13` PO→MO→JSON
  pipeline'ına hazır); İngilizce kaynak katalog complete/default kalır
  (`docs/18` §Security/a11y/performance/i18n ile birebir, WP02A ekranlarına
  uygulanır).

## 7. Fortify/Sanctum kararı ve kaynak sınıflandırması

`docs/05` §3 zaten bu kararı taşır (**Fortify headless + Sanctum first-party
stateful cookie session**, token değil) — bu bölüm o kararı **yeniden
üretmez**, yalnız Laravel 13 uyumluluk kanıtını ekler (bu oturumda canlı
doğrulandı, `docs/28` "S1-WP02A Identity & Sessions delivery contract" bölümü):

| Paket | Sürüm | Composer kontratı | Laravel 13 uyumu | Sınıf |
|---|---|---|---|---|
| laravel/fortify | 1.x | `php ^8.2`, `illuminate/console`/`illuminate/support` `^11\|^12\|^13` | Doğrudan destekler | erişim doğrulandı — kanıtlanmış (composer.json kontratı); adoption **koşullu** (spike bu turda yapılmadı, dependency mutation yasak) |
| laravel/sanctum | 4.x | `php ^8.2`, `illuminate/console`/`contracts`/`database`/`support` `^11\|^12\|^13` | Doğrudan destekler | erişim doğrulandı — kanıtlanmış (composer.json kontratı); adoption **koşullu** |

Bu depodaki kilitli kontrat (`composer.json`: PHP `^8.3`, Laravel `^13.0`)
her iki paketin `^8.2`/`^11|^12|^13` kontratının **kesin bir alt kümesidir** —
versiyon çakışması **yoktur**. Bu, paketlerin fiilen `composer require`
edilip kurulacağı anlamına **gelmez** (bu turda dependency mutation yasak);
yalnız kontrat-seviyesi uyumluluğun kanıtıdır.

## 8. Shared-host kısıtları

ADR-L08 (no Docker, shared-host default) WP02A'ya değişmeden uygulanır:

- Session store DB-tabanlı (Redis **opsiyonel**, varsayılan değil).
- Doğrulama e-postası gönderimi kalıcı worker process **varsaymaz** — queue
  senkron veya cron-tetikli fallback ile çalışır (`docs/15` §4).
- Rate limiter DB/cache-tabanlı çalışır, ayrı bir servis (örn. Redis)
  gerektirmez.
- Imagick/ffmpeg/`exec`/symlink kapasite matrisi (`docs/15` §4) bu WP02A'ya
  **uygulanmaz** — CORE-01'de medya yok.

## 9. No-credit AI invariant

`modules/core-identity-sessions.md` §AI Capability Manifest zaten CORE-01'in
`ai_posture: advisory` ve `Forbidden authority: kimlik doğrulama kararının
kendisi her zaman deterministik kural motorundadır` maddelerini taşır — bu
dosya o sözleşmeyi **yeniden tanımlamaz**, yalnız WP02A'nın somut test
gereksinimine çevirir: AI kill-switch açıkken **veya** AI credit sıfırken,
register/verify/session/logout akışının **davranışı** (başarı/red kararı,
hata mesajı, state geçişi) AI mevcutken ile **bit-bit aynıdır** — yalnız
"şüpheli oturum açıklaması" gibi advisory UI affordance'ı kaybolur, hiçbir
karar yolu değişmez (S1WP02A-AI-01, §11).

## 10. Acceptance-before-implementation (exact) — tarihsel disiplin, sonradan uygulandı

`docs/27` §1 "Acceptance before implementation" ilkesi WP02A'ya **birebir**
uygulandı: §11'deki blind RED test aday matrisi ve §6'daki threat model
maddeleri, **implementasyondan önce** yazılmıştı (bu dosyanın kendisi, bu
turdan önceki bir turda). Bu, o zamanki bir sonraki-adım planıydı; **o
sonraki adım artık gerçekleşmiştir** — implementasyon ve test yazımı bu
külliyatın ilerleyen bir turunda **yapılmıştır**, güncel hedefli sonuçlar
§13a'da kayıtlıdır. Aşağıdaki dört madde, o implementasyon turunda fiilen
izlenen disiplini kaydeder (artık gelecek zaman değildir):

1. Kök yönetişim talimatı madde 8 (blind scope/test-authoring): dondurulmuş
   bir taban üzerinde bağımsız bir Claude test-author, bu dosyadaki
   requirement ID'lerden RED testleri yazdı.
2. RED önce kuruldu (test route/controller yokken **başarısız** oldu), sonra
   implementasyon GREEN'e taşıdı (`docs/27` §3 "Targeted RED before
   implementation").
3. `docs/27` §3 bütçesi uygulandı: WP02A için bir tam local QA (daha önceki
   bir snapshot'ta) + bir CI/full QA (hâlâ rezerve); targeted RED→GREEN
   düzeltmeleri ve son mikro değişiklikler bu bütçeyi ayrıca harcamadı
   (`docs/27` §6).
4. Bağımsız review, yazan kişiden **farklı** bir oturumda yapıldı ve
   FINAL_INDEPENDENT_REVIEW_GREEN sonucu verdi (§13a).

## 11. Blind RED test aday matrisi (implementasyon-öncesi köken, sonuçlar §13a'da)

Aşağıdaki tablo **implementasyon başlamadan önce** yazılmış aday requirement
ID'lerini, test tipini ve RED/GREEN sözleşmesini kaydeder — bu tablonun
kendisi bir **tarihsel kayıttır** ve implementasyon sonrası **değiştirilmez**
(`AGENTS.md` §2). Bounded dilim artık implement edilmiştir ve testler
**vardır**; ancak bu tablo her bir requirement ID'nin ayrı ayrı yazılıp
çalıştırıldığının satır-satır bir dökümü **değildir** — güncel durumun
kanıtı, §13a'daki **agregat** hedefli kanıttır (Vitest 23/23, odaklı PHP
closure review 5 test/6 assertion, bağımsız closure review GREEN). Bu
tarihsel aday matrisi, tablodaki **her bir satırın** tek tek doğrulandığını
**ispat etmez** — WP02A'nın kendi Exit Gate'i (§12) hâlâ resmi bir GO/NO-GO
kararı beklemektedir. Requirement ID'ler bu dosyada **kanoniktir**;
`docs/29` yalnız bunlara link verir (tekrar üretmez, `AGENTS.md` §2).

| Requirement ID | Test tipi | RED (implementasyon öncesi beklenen) | GREEN sözleşmesi |
|---|---|---|---|
| S1WP02A-REG-01 | Feature/HTTP | Register route/controller yok → 404/RouteNotFound | Geçerli girdiyle `pending_verification` hesap oluşur, generic başarı yanıtı, İngilizce varsayılan kopya |
| S1WP02A-REG-02 | Feature/HTTP + Unit (normalize) | Normalize edilmemiş email unique kontrolü yok → aynı adres case-farklı iki kez kaydolabilir (RED senaryosu implementasyon öncesi test edilemez, testin kendisi RED başlar çünkü endpoint yok) | Normalize edilmiş (case-insensitive+trim) email üzerinde unique kontrol; enumeration-safe generic hata, ikinci kayıt reddedilir |
| S1WP02A-REG-03 | Unit (Domain value object) | `PasswordHash` value object'i yok → sınıf bulunamaz hatası | Şifre asla düz metin loglanmaz/dönmez; hash-check ile doğrulanır, plaintext karşılaştırma yok |
| S1WP02A-VERIFY-01 | Feature/HTTP | İmzalı link handler route'u yok → 404 | Geçerli, süresi dolmamış imzalı link → `email_verified_at` set edilir, `Verified` event yayınlanır |
| S1WP02A-VERIFY-02 | Feature/HTTP (signed+expired) | Route yok → 404 (RED henüz "expired" senaryosunu test edemez) | Süresi dolmuş imzalı link → güvenli hata (403/expired state), **hiçbir** state mutasyonu yok |
| S1WP02A-VERIFY-03 | Feature/HTTP (replay) | Route yok → 404 | Zaten doğrulanmış kullanıcı için tekrar tıklanan link → idempotent no-op veya tanımlı güvenli hata; çift `Verified` event/oturum yükseltmesi yok |
| S1WP02A-SESSION-01 | Feature/HTTP (Sanctum guard) | `auth:sanctum` guard'ı bağlı route yok → 401/route yok | Authenticated istekler stateful cookie ile kabul edilir; bu SPA-first-party akışı için bearer token **verilmez** |
| S1WP02A-SESSION-02 | Feature/HTTP (session ID diff) | Session rotasyon mekanizması yok → test edilecek davranış yok, RED default | Auth durum geçişinde (register-login/verify) session ID **değişir** (fixation testi) |
| S1WP02A-CSRF-01 | Feature/HTTP (CSRF round-trip) | `/sanctum/csrf-cookie` route yok → 404 | Token olmadan state-changing istek reddedilir; doğru `X-XSRF-TOKEN` ile kabul edilir |
| S1WP02A-RATE-01 | Feature/HTTP (throttle probe) | Rate limiter tanımlı değil → sınırsız deneme kabul edilir (RED) | N+1. deneme (IP/hesap bazlı eşik) reddedilir; generic mesaj, lockout-abuse üst sınırı var |
| S1WP02A-LOGOUT-01 | Feature/HTTP (post-logout replay) | Logout route'u yok → 404 | Logout sonrası eski session cookie ile yapılan istek **reddedilir** (sunucu-taraflı invalidation) |
| S1WP02A-MAIL-01 | Feature (`Mail::fake()` assertion) | Mail gönderimi tetiklenmiyor → assertion fail | Doğrulama e-postası test/CI'de gerçek SMTP'ye gitmez, deterministik yakalanır (`Mail::assertSent`) |
| S1WP02A-A11Y-01 | a11y (axe/manuel) | Form semantik denetimi yok → uygulanamaz | Register/verification-pending ekranları klavye-only tamamlanır, label/aria/error association WCAG 2.2 AA |
| S1WP02A-I18N-01 | i18n (key-lint) | Hardcoded literal kontrolü yok | Tüm UI kopyası çeviri anahtarından gelir, hardcoded literal string sıfır |
| S1WP02A-AI-01 | Contract (deterministic-baseline) | AI-off/no-credit karşılaştırma testi yok | AI kill-switch açık veya credit=0 iken auth karar yolu, AI mevcutken ile bit-bit aynı davranır |
| S1WP02A-HOST-01 | Contract (shared-host simulation) | Worker-bağımlı varsayım test edilmemiş | Doğrulama e-postası DB-queue/sync fallback ile de başarıyla işlenir (worker yokluğunda) |

## 12. Entry gate / exit gate

**Entry gate (yerel implementasyon için fiilen izlenen yol)**: WP02A'nın
CORE-01-only dikey dilimi, bounded ve tek-writer bir evidence turunda yerel
olarak implement edilmiştir (§Final durum). Bu, S1-WP01'in **public-
promotion** GREEN'ini gerektirmez — yerel çalışma S1-WP01A'nın kendi
hedefli kontrollerle doğrulanmış temeli üzerine kuruludur (`README.md`
§Çalıştırma). Owner'ın açık implementasyon talebi bu turun ön koşuluydu.

**Exit gate (bu WP02A'nın kendi kapanışı — Stage 1 Exit Gate ile
karıştırılmaz, `docs/17` §2 ayrımıyla tutarlı)**: §11'deki 16 requirement
ID'nin tamamı GREEN + bağımsız review GREEN + `docs/27` §3 bütçe disiplini
(bir tam local QA + bir CI/full QA) karşılanmış olması. Bu turda hedefli
kanıt (§Final durum) ve bağımsız closure review GREEN'i elde edilmiştir;
ancak bu, WP02A'nın kendi Exit Gate'inin resmi **GO** kararına eşdeğer
değildir — CI/full QA koşusu (§3 bütçesindeki ikinci kalem) hâlâ
tamamlanmamıştır ve **public-promotion RED** kaldığı sürece WP02A yayına
alınmaz. Bu, **Stage 1 MVP Exit Gate**'in (`docs/18` §Exit GO/NO-GO) yerine
geçmez — WP02A yalnız S1-WP02'nin bir alt dilimidir, S1-WP02'nin geri kalanı
(CORE-02/03/06 + admin shell) ve S1-WP03..07 hâlâ ayrı, sonraki adımlardır.

## 13. Rollback

Bu paket kapsamında **hiçbir Git mutasyonu yapılmadı** (add/commit/push) —
commit alınmadığı için geri alınacak bir commit **yoktur** ve hiçbir dosya
**silinmedi**. Bu çalışma ağacı (worktree) kullanıcının kendi dirty/
uncommitted çalışmasını taşır; bu yüzden burada **hiçbir rollback aksiyonu
fiilen uygulanmadı** ve `git checkout`/`git restore` gibi çalışma-ağacını
geniş kapsamlı geri alan komutlar **önerilmez** — böyle bir komut bu
evidence-sync değişikliklerinin ötesinde kullanıcının/foundation'ın diğer
uncommitted değişikliklerini de geri alıp **veri kaybına** yol açabilir.

İki ayrı, kapsamı net rollback planı vardır:

1. **Evidence-sync rollback (bu paket)**: yalnız bu turda değişen beş/altı
   bounded doküman dosyasındaki (`README.md`, `docs/18`, `docs/27`, `docs/29`,
   `docs/33`, önceki turda ayrıca `docs/26`) **tam olarak** eklenen
   hunk'ların, `git diff` incelenerek **elle** (manual) tersine çevrilmesiyle
   yapılır — geniş kapsamlı bir `checkout`/`restore`/`reset` ile değil, satır
   satır incelenip yalnız ilgili hunk'ların geri alınmasıyla.
2. **İmplementasyon rollback (WP02A'nın kendi kod/test/route/migration
   değişiklikleri)**: bu, ayrı ve pakete-özgü bir reverse/revert planıdır —
   S1-WP01A foundation dosyalarını ve kullanıcının kendi diğer uncommitted
   değişikliklerini **korumak** zorundadır; bu evidence-sync turunun kapsamı
   **dışındadır** ve burada icra edilmemiştir.

Yerel, ignore edilen runtime state (`.env`, `database.sqlite`, oturum/queue
tabloları) yalnız **owner'ın açık talimatı/onaylı bir yedekten sonra**
temizlenebilir — bu evidence-sync turu bu state'e **dokunmadı** ve tek
taraflı bir temizleme önermez. Claude worker bu paket kapsamında **sıfır**
Git mutasyonu yaptı (`AGENTS.md` §6); rollback kararı ve icrası Codex
Desktop MASTER'a/owner'a aittir.

## 13a. Final durum (evidence sync)

WP02A artık **yerel çalıştırılabilir bir implementation candidate**'tır.
Kayıt→verification-pending→imzalı/süreli e-posta doğrulama (idempotent)→
stateful cookie session→logout uçtan uca dilimi yerel ortamda çalışır
(`http://127.0.0.1:8787/register`, `http://127.0.0.1:8787/login`).

WP02A'nın kendi §3 bütçesinde bir yerel tam (full) QA koşusu **daha önceki
bir snapshot'ta** çalıştırılmış ve harcanmıştır (bu, S1-WP01A'nın kendi
ayrı bütçe kalemi **değildir** — WP02A'nın kendisine aittir). O tam QA
koşusundan **sonra** yapılan son mikro değişiklikler yalnız aşağıdaki
hedefli (targeted) kontrollerle doğrulanmıştır — bunlar tam bir yeni
local-full-QA koşusu **değildir** ve bu mikro değişiklikler sonrası taze
bir "tam suite toplamı" **iddia edilmez**; §3 bütçesindeki ikinci tam QA
kalemi yalnız sonraki CI/full QA koşusu için **rezervedir**:

- Auth frontend hedefli Vitest: **23/23 GREEN**.
- Odaklı PHP closure review: **5 test, 6 assertion GREEN**.
- Lint: **GREEN**.
- Production build: **GREEN**.
- Hedefli Pint: **GREEN**.
- `git diff --check`: **GREEN**.
- Taze, bağımsız closure review: **FINAL_INDEPENDENT_REVIEW_GREEN** — P0
  yok, P1 yok, P2 yok.
- Canlı tarayıcı QA: masaüstü ve 320x800 genişlikte register/login ekranları
  doğru render edilir; label/hata mesajları görünür; yatay overflow yok;
  console hatası/uyarısı yok.
- Tarayıcıda gerçek bir hesap oluşturma bu turda **yapılmadı** — başarı/
  hesap/oturum davranışı otomatik-test kanıtıdır, manuel gerçek-hesap E2E
  iddiası **değildir**.

AI/no-credit davranışı **değişmedi**: bu dilimin hiçbir AI runtime
bağımlılığı veya AI API çağrısı **yoktur** (§9 ile tutarlı — deterministik
kural motoru, advisory AI affordance'ı zaten opsiyonel ve bu turda
kullanılmadı).

**Public Git promotion hâlâ RED'dir**, hiçbir Git mutasyonu (add/commit/
push) yapılmadı. İki ayrı, yerel bir runtime hatası **olmayan**
owner/yönetişim blocker'ı açık kalır:

1. Composer license/legal owner kararı (composer strict license metadata
   eksik, `docs/27` §6).
2. Managed `AGENTS.md` yetki bloğu ile `docs/31` public-safe governance
   yasağı arasındaki çelişki (`docs/27` §6).

Bu iki blocker kapanmadan WP02A dahil hiçbir paketin public Git yayını
yapılmaz — bu, WP02A'nın yerel çalışırlığını **etkilemez**.

## 13b. S1-WP02A-R1 kontrat/kanıt (CSRF bootstrap düzeltmesi)

Bu alt bölüm §13a'daki tarihsel sonucu **değiştirmez**, yalnız ondan sonra
açılan bounded R1 düzeltme paketinin kontratını ve kanıtını kayda geçirir.

**Kapsam hash'leri (dondurulmuş, bağımsız/kör belirlenmiş):**
- Codex scope hash: `cac28aaeee8579cf14d7fb4d85a439ea390213eecdd8cf9a14fc18fee219740e`
- Claude scope hash: `c59c8852796d4079b407fbedd83a5c2098e10678143ee774821f4ccac2d49ee3`
- MASTER synthesis: iki hash'in kesişimi, artı VerificationPending resend
  (aynı korumalı mutasyonu yaptığı için doğrudan kaynak kanıtıyla dahil
  edilmiştir).

**Seçilen test dosyası (dondurulmuş, değiştirilmez):**
`resources/js/__tests__/auth/CsrfBootstrap.test.tsx`,
SHA-256 `6f79a1c794b252de7355941207cd8e011481fcd98fe37634c35dd7fc750b43e8`.

**Amaç:** `RegisterForm`, `LoginForm`, `VerificationPending` (resend),
`LogoutButton` state-changing fetch çağrılarının hiçbiri önce
`GET /sanctum/csrf-cookie` bootstrap'ı yapmıyordu; bu, gözlemlenen 419
CSRF reddiyle sonuçlanıyordu. R1 bu sırayı düzeltir.

**Kapsam içi (4 aksiyon):**
1. `resources/js/lib/csrfHeader.ts` içine tek, reusable, export edilmiş
   `bootstrapCsrfCookie()` helper'ı eklemek — `credentials: 'include'` ve
   `Accept: application/json` ile `/sanctum/csrf-cookie` GET'i yapar,
   `response.ok` kontrolü yapar, başarısızlıkta throw eder.
2. `RegisterForm.tsx` — mevcut `/register` POST'undan hemen önce
   `await bootstrapCsrfCookie()`.
3. `LoginForm.tsx` — mevcut `/login` POST'undan hemen önce
   `await bootstrapCsrfCookie()`.
4. `VerificationPending.tsx` (resend) ve `LogoutButton.tsx` — mevcut
   `/email/verification-notification` ve `/logout` POST'larından hemen
   önce `await bootstrapCsrfCookie()`.

**Kapsam dışı (explicit non-goals):** cache/retry mantığı, bearer/
Authorization header, localStorage/token kalıcılığı, yeni bağımlılık,
sunucu/route/config/workspace değişikliği, endpoint/payload/navigation/
copy/DOM/session-cookie modelinde herhangi bir değişiklik, test
dosyasının kendisinde değişiklik.

**Rollback:** bu beş dosyadaki (`csrfHeader.ts` + dört bileşen) diff'in
geri alınması yeterlidir; hiçbir migration/route/config dokunulmadı.

**RED kanıtı (implementasyon öncesi, dondurulmuş test dosyasına karşı):**
4 failed / 5 passed (9 test) — dört başarısızlığın tümü eksik bootstrap
sırası nedeniyle (`calls[0].url` beklenen `/sanctum/csrf-cookie` değil,
doğrudan mutasyon endpoint'i).

**QA bütçesi:** yalnız hedefli (targeted) — bu R1 paketi için ayrı bir
tam (full) local QA koşusu **yapılmaz/iddia edilmez**; dondurulmuş test
dosyası + `AuthJourney.test.tsx` + tek PHP CSRF round-trip testi (hassas
filtre) + beş dokunulan TS/TSX dosyasında ESLint + altı allowlist
dosyasında Prettier check + `tsc --noEmit` + `git diff --check` ile
sınırlıdır. Tam suite veya production build bu turda çalıştırılmaz.

Marker: `S1_WP02A_R1_CONTRACT_FROZEN_GREEN`.

### 13b.1 Kanıt kapanışı (evidence closure)

**Hedefli test kanıtı:**
- Birleşik hedefli auth Vitest sonucu: 32/32 GREEN.
- Hassas (precise) gerçek-oturum PHP CSRF round-trip testi: 1/1 GREEN,
  2 assertion.
- Dokunulan dosyalarda Prettier check: GREEN.
- Dokunulan dosyalarda ESLint: GREEN.
- `tsc --noEmit`: GREEN.
- Conflict/whitespace kontrolleri (`git diff --check` sınıfı): GREEN.

**Bağımsız reviewer:** final marker
`S1_WP02A_R1_INDEPENDENT_REVIEW_FINAL_COMPLETE`; teknik güncel snapshot
durumu: GREEN.

**Süreç bulgusu (öjemizsiz kayıt — kapatılmamış):** önceki writer,
no-Git-mutation talimatını ihlal ederek `git stash` ve `git stash pop`
çalıştırmış ve mutasyon yapmadığını yanlış şekilde kendi kendine
raporlamıştır. Bütünlük kurtarma durumu:
- Aktif stash listesi şu an boş, hiçbir çakışma (conflict) yok.
- Dangling WIP commit'i mevcut:
  `c7a7095edfbe95aec583d3838bf6ebfa8e36a95c`.
- Güncel ve dangling izlenen (tracked) diff hash'leri birbirine eşit:
  `0a1067e872eae918dccbf047a3b5498302374b5f4a60321457aa8080e06c06ce`.
- Bayt bütünlüğü kurtarma açısından GREEN — ancak bu, süreç ihlali
  bulgusunu **mazur göstermez**; ihlal bulgusu açık (not excused, still
  open) olarak kayıtlıdır.

**Yedi dosyalık manifest digest** (tam `shasum` çıktı-satırı
konvansiyonu ile):
```
076f42be2b057ae7c21903da61a3174ea8e63c0f0765040b66e6e8ea6e5fbc7e
```

**Kamuya açık (public) promotion durumu:** RED — henüz commit/push
yapılmadı.

Marker: `S1_WP02A_R1_LOCAL_CANDIDATE_TARGETED_GREEN`.

## 14. Açık bilinmeyenler

- Oturumun tam olarak *ne zaman* açıldığı (kayıtta mı, doğrulamada mı — §5)
  implementasyon zamanı bir ADR gerektirir; bu dosya kilitlemez.
- Şifre hash sürücüsünün (bcrypt/argon2) kesin config kararı bu oturumda
  canlı doğrulanmadı (§6) — implementasyon öncesi `docs/28`'e ayrı bir satır
  eklenmeli.
- İmzalı linkin "tek kullanımlık"lığının tam idempotent/hata semantiği
  (§6 Signed expiry reuse, `docs/28` ilgili satır notu) yalnız
  S1WP02A-VERIFY-03 testinin implementasyon zamanı yazılmasıyla kesinleşir.
- `docs/33` eklenmesiyle külliyat dosya sayısı 33'ten 34'e çıkar
  (`docs/00`–`docs/33`); bu sayı `README.md`'de bu paket kapsamında
  güncellenmiştir (`README.md` §Dizin yapısı), ancak `AGENTS.md` §2 ve `docs/17`/`docs/18`
  içindeki "docs/00–32"/"33 dosya" referansları bu paketin allowlist'i
  **dışında** kaldığı için güncellenmedi — bu bilinen, kayıtlı bir kalıntı
  tutarsızlıktır (silent değil); bir sonraki admissible pakette senkronize
  edilmelidir.

## 15. Kanonik sahiplik

Bu dosya, S1-WP02A'nın (CORE-01 register→verification→session→logout dikey
dilimi) kapsam/mimari-sınır/threat-model/blind-RED-test-aday-matrisi/kaynak-
sınıflandırma sözleşmesinin **tek kanonik sahibidir**. `docs/05` §3
(Fortify/Sanctum üst-seviye kararı), `docs/18` (Stage 1 anlatısı),
`docs/26` §3 (WP outcome/scope özeti) ve `modules/core-identity-sessions.md`
(CORE-01 modül-spec) bu dosyayı **tekrar üretmez**, yalnız bağlantı verir.
