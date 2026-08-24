# 27 — QA, Acceptance & Vibecoding Discipline

**QA/kabul disiplin sözleşmesi.** Bu depo dört farklı kabul rejimine
tabidir: (1) planning-only plan korpusu (docs-only yapısal kabul); (2)
S1-WP01A foundation — `/up` health route'u + foundation-status ekranı,
hedefli kontrollerle doğrulanmış; (3) S1-WP02A kimlik/oturum — çalışan
`/register` ve `/login`, hedefli test/review kanıtıyla; (4) S1-WP02B
tenancy baseline — **yalnız API kodu + izole hedefli test koşusu** kanıtı
(persistent DB migrate edilmedi, tarayıcı/UI journey yok, bkz. §6). Bu
doküman dört rejim için de kanıt/kabul **disiplinini** tanımlar; kendisi bir
tam test raporu **değildir** — gerçek kanıt/kontrol sonuçları §6'da,
`docs/33` §Final durum'da ve `docs/34` §13a'da özetlenir.

## 1. Waterfall QA disiplini

- **Baseline/change control**: Her değişiklik bir ADR veya requirement ID'sine
  bağlanır.
- **Requirements ID**: Her gereksinim `docs/29-TRACEABILITY-MATRIX.md`'de bir
  kimliğe sahiptir.
- **Acceptance before implementation**: Kabul kriteri, implementasyondan
  **önce** yazılır (bu külliyatta zaten böyle — modül spec'leri implementasyon
  yokken yazıldı).
- **Source/assumption/evidence ayrımı**: Her iddia şu üçünden biri olarak
  etiketlenir: **source** (birincil kaynağa dayalı, `docs/28`), **assumption**
  (varsayım, `docs/16`'da kayıtlı), **evidence** (gerçek test/ölçüm sonucu).
  Külliyatın planning-only kalan bölümleri için evidence hâlâ **yoktur**
  (implementasyon yok); S1-WP01A, S1-WP02A ve S1-WP02B için ise artık gerçek
  evidence **vardır** (hedefli test/build/review kanıtı, bkz. §6).

## 2. İzlenebilirlik zinciri

```
Requirements → Module → Journey → Stage → Work Package → Acceptance → Test → Rollback
```

Çift yönlü: bir test'ten geriye doğru hangi requirement'ı doğruladığı, bir
requirement'tan ileriye doğru hangi test'in onu doğrulayacağı izlenebilir
(`docs/29`).

## 3. AI-generated change disiplini (implementasyon paketlerine uygulanır — S1-WP01A/S1-WP02A/S1-WP02B implementasyonu başladığı için artık fiilen geçerlidir)

- Scoped prompt (kapsamı net tanımlı).
- Allowlist (yalnız izin verilen dosya/eylem).
- No secret (prompt'a secret sızdırılmaz).
- Single writer (bir değişiklik paketinin tek yazarı).
- Deterministic diff (aynı girdi aynı çıktıyı üretir).
- Targeted RED before implementation (önce başarısız test yazılır).
- **Bütçe**: bir writer'ın bir değişiklik paketi için bir tam local QA
  çalıştırması + bir CI/full QA (normal bütçe, tekrar gerektiren durum
  gerekçeli kaydedilir — bkz. kök yönetişim talimatı madde 9).
- Independent review (yazan kişi kendi paketini review edemez).
- Visual/a11y/security/tenant/payment/restore kapıları implementasyon
  başladığında zorunlu.

## 4. "Vibe says done" reddi

Bir worker'ın "tamamladım" beyanı **kanıt değildir**. Kanıt: çalışan test
çıktısı, ölçülmüş metrik, bağımsız review sonucu. Bu külliyatın kendisi de bu
kurala tabidir — `README.md`'nin "0/8" sayacı bu yüzden plan üretiminden
etkilenmez.

## 5. Zorunlu test kategorileri (implementasyon paketi kapsamıyla ilgili olduğunda uygulanır — S1-WP01A/S1-WP02A/S1-WP02B için ilgili kategoriler (tenant escape testi dahil) artık fiilen uygulanmıştır)

Threat model, migration/fixture testleri, contract testleri, **property-based
money testleri** (`docs/09` deterministik para politikasının otomatik
doğrulaması), QR fiziksel scan matrisi (`docs/08` §4), medya golden-file
testleri (`docs/07`), ECA recursion/cycle guard testi (`docs/10` §3), auth
policy matrix testi (`docs/05` §2), tenant escape testi (`docs/05` §6), webhook
replay/idempotency testi (`docs/09` §5), i18n pseudolocale/RTL testi
(`docs/13`), erişilebilirlik testi (`docs/15` §6), yük/degradation testi
(`docs/15` §4), backup restore testi (`docs/15` §4, `docs/16` DR-02).

## 6. Bu paketin kendi durumu

Bu depo artık dört farklı kabul rejimine tabidir. 35 dosyalık plan
korpusunun S1-WP01A/S1-WP02A/S1-WP02B dışında kalan bölümleri
(`docs/00`–`docs/32`, `docs/33`/`docs/34`'ün kendi kapsam/threat-model
içeriği hariç, `modules/`, `skills/`, `templates/`) hâlâ **yalnız docs-only
yapısal kabul**e tabidir: dosyaların var olup olmadığı, thin/placeholder
olup olmadığı, link'lerin çalışıp çalışmadığı, sekiz aşamalı sıranın doğru
olup olmadığı (bkz. `README.md` self-check, madde M) — runtime test bu
alanlar için **N/A**'dır çünkü runtime kodu yoktur.

**S1-WP02A targeted-evidence istisnası**: `docs/33`'teki kapsam sözleşmesi
artık yalnız docs-only değildir — CORE-01-only register→verification-
pending→signed/expiring email verification→authenticated cookie session→
logout dikey dilimi yerel olarak implement edilmiş ve hedefli kanıtla
GREEN'e taşınmıştır (ayrıntı §6 aşağıda ve `docs/33` §Final durum, §13a).
WP02A'nın **kendi** §3 bütçesi vardır — bu S1-WP01A'nın bütçesinden
**ayrıdır** ve onunla karıştırılmaz: WP02A için bir yerel tam (full) QA
koşusu daha önceki bir snapshot'ta çalıştırılmış ve harcanmıştır. Bu tam
QA koşusundan **sonra** yapılan son mikro değişiklikler yalnız **hedefli**
(targeted) kontrollerle doğrulanmıştır — auth frontend Vitest 23/23, odaklı
PHP closure review 5 test/6 assertion, lint/build/Pint/`git diff --check`
GREEN, bağımsız closure review — bunlar tam bir yeni local-full-QA koşusu
**değildir**; bu mikro değişiklikler sonrası taze bir "tam suite toplamı"
iddia **edilmez**. WP02A'nın §3 bütçesindeki **ikinci** tam QA kalemi
yalnız sonraki CI/full QA koşusu için **rezervedir**.

S1-WP01A foundation iskeleti (`app/`, `config/`, `routes/`, `resources/`,
`tests/`) için ise runtime test **N/A değildir** — gerçek komutlarla
çalıştırılır: `php tests/preflight/s1-wp01a-foundation-preflight.php`
(bağımsız RED/GREEN gate), `php artisan test` (PHPUnit), `npm test`
(Vitest), `npm run lint`, `npm run build`. Bu paketin kendi durumu
**implementation-in-progress**tir: §3'teki bütçenin bir tam yerel QA
çalıştırması kalemi harcanmıştır — FULL_QA_LOCAL_1 bir kez çalıştı, 8/10
GREEN (Gate 1 `composer validate --strict` license metadata eksikliğiyle
exit 1 verdi, Gate 3 Pint stil sapmasıyla fail oldu). Sonraki hedefli
(targeted, tam QA değil) düzeltme Gate 3'ü mevcut snapshot'ta GREEN yaptı
(4 dosya `vendor/bin/pint --test` green; 8 test/94 assertion + preflight
17/17 green); bu hedefli düzeltme §3 bütçesinden ikinci bir tam yerel QA
harcaması sayılmaz — ikinci tam QA bütçe kalemi yalnız sonraki CI/full QA
koşusu için rezervedir, ikinci bir yerel tam koşu planlanmaz (kör/tekrar
local full QA §3 disiplinine aykırıdır). Gate 1 (`composer validate
--strict`) yalnız composer strict license metadata/owner kararı
eksikliğiyle RED kalır (license field yok).

FULL_QA_LOCAL_1/Pint hedefli düzeltmesinden **sonra**, ayrı bir MVC/OOP
gap için targeted RED→GREEN yapıldı: GET '/' route'u routes/web.php'de
closure yerine `App\Http\Controllers\FoundationStatusController`'a
bağlandı (final sınıf, `declare(strict_types=1)`, public `__invoke`,
docs/03 ADR-L02/ADR-L03). Hedefli `FoundationStatusDeliveryArchitectureTest`
+ `HealthCheckRouteTest` 6/6 test, 16 assertion GREEN'dir. Bu targeted
düzeltme §3 bütçesindeki tam local QA kalemini harcamaz — tam QA bu
snapshot için tekrar çalıştırılmadı; ikinci tam QA bütçe kalemi yine
yalnız sonraki CI/full QA koşusu için rezervedir ve o CI koşusu bu
delta'yı da kapsamak zorundadır.

İlk bağımsız review çalıştı ve INDEPENDENT_REVIEW_RED sonucu verdi. İki P1
owner-kararı blocker'ı tespit etti: (1) composer strict license
metadata/owner kararı (yukarıda), (2) public-governance çelişkisi —
`AGENTS.md`'deki managed Pane session/panel detay bloğu `docs/31`'in
public-safe yasağıyla çelişir; bu blok bu paketten önce vardır ve owner
istemeden kaldırılmaz, bu paket onu değiştirmez (ileriye dönük kaldırma da
public Git geçmişini silmez). Aynı review iki P2 bulgu da tespit etti,
ikisi de hedefli RED→GREEN kanıtla düzeltildi:

- ADR-L03 `strict_types`/default-final uygulama sınırı, abstract extension
  point istisnasıyla; hedefli `StrictOopApplicationBoundaryTest` GREEN.
- Core module badge numaratörü artık `config('core-modules')`'tan
  controller → Blade data attribute → React required prop zinciriyle
  türetiliyor; hardcoded `CORE_MODULE_COUNT` numaratör kaldırıldı; hedefli
  MVC/health ve AppShell testleri GREEN.

P2 düzeltmelerinden sonra, dondurulmuş snapshot
`aaf247029d4571fd5347ee24a20d1ffd09a104992103d8f68ad3ca3e7a6a2564` (209
dosya) üzerinde ikinci, bağımsız bir review tamamlandı ve yine
INDEPENDENT_REVIEW_RED sonucu verdi. Bu ikinci review, önceki iki P2
kapanışının (A: ADR-L03 strict_types/default-final sınırı, B: core module
badge numaratörü) ikisini de kendi hedefli kontrolleriyle GREEN
doğruladı: reviewer'ın hedefli PHPUnit koşusu 10/10 test, 34 assertion
GREEN; hedefli Vitest koşusu 7/7 GREEN. RED sonucu, P2 bulgularından
değil, aynı iki P1 owner-kararı blocker'ının (composer license metadata +
AGENTS.md/docs/31 public-governance çelişkisi) hâlâ açık olmasından
gelir — bu review üçüncü bir owner-kararı blocker'ı tespit etmedi.

P2 sonrası ayrı, hedefli bir `npm run build` koşusu da GREEN'dir: Vite
8.2.1, 257 modül, exit 0 — bu bir tam local QA koşusu **değildir**,
yalnız targeted build kanıtıdır; §3 bütçesindeki ikinci tam QA kalemini
harcamaz. Bu build üzerinde yapılan masaüstü tarayıcı smoke testi de
GREEN'dir: HTTP 200, registry-türevi sayaçlar 16/16 ve 0/8 doğru,
semantic `nav`/`main`/`h1`/`lang=en` mevcut, yatay overflow yok.

Durum implementation-in-progress / promotion RED / yayın yok olarak
kalır — "vibe says done" reddi (§4) burada da geçerlidir. Yayın/
promotion, yalnız yukarıdaki iki P1 owner kararı (composer license/legal
duruş; managed AGENTS.md Pane bloğu vs `docs/31` public-safe yasağı)
kapanana kadar RED'dir; managed blok bu paketten önce vardır, owner
istemeden kaldırılmaz ve ileriye dönük kaldırma da public Git geçmişini
silmez.

**S1-WP02A'nın kendi durumu**: CORE-01-only register→verification-pending→
signed/expiring email verification→authenticated cookie session→logout
dikey dilimi yerel olarak implement edilmiş ve çalıştırılabilir haldedir
(`http://127.0.0.1:8787/register`, `http://127.0.0.1:8787/login`). Hedefli
kanıt: auth frontend Vitest 23/23 GREEN; odaklı PHP closure review 5 test/6
assertion GREEN; lint GREEN; production build GREEN; hedefli Pint GREEN;
`git diff --check` GREEN; taze bağımsız closure review sonucu
FINAL_INDEPENDENT_REVIEW_GREEN — P0 yok, P1 yok, P2 yok. Manuel tarayıcı
QA: masaüstü ve 320x800 genişlikte register/login ekranları doğru render
edilir, label/hata mesajları görünür, yatay overflow yok, console
hata/uyarı yok. Bu turda tarayıcıda gerçek bir hesap oluşturma **yapılmadı**
— başarı/hesap/oturum davranışı otomatik test kanıtıdır, manuel gerçek-
hesap E2E iddiası **değildir**. WP02A'nın kendi §3 bütçesinde bir yerel
tam QA koşusu daha önceki bir snapshot'ta zaten harcanmıştı (bu, WP02A'nın
kendisine aittir — S1-WP01A'nın ayrı bütçe kaleminden bağımsızdır, bkz.
S1-WP01A'nın kendi ayrı tarihçesi yukarıda); o tam QA koşusundan sonraki
son mikro değişiklikler yalnız hedefli kontrollerle doğrulandı. İkinci tam
QA kalemi yalnız CI/full QA koşusu için rezerve kalır — final mikro
düzeltmelerden sonra taze bir "tam suite toplamı" iddia edilmez. Durum: **WP02A
local-candidate-targeted-green**, **public-promotion RED**. Public Git
promotion RED'i S1-WP01A'nın iki P1 owner-kararı blocker'ıyla **aynı**
kalır — bu iki blocker bir yerel runtime hatası **değildir**, ayrıntı
`docs/33` §Final durum.

**S1-WP02B'nin kendi durumu**: Bounded CORE-02 tenancy baseline (workspace
create+owner-membership tek transaction, üyelik-scope'lu liste,
current/switch context, enumeration-safe tenant escape reddi) yerel olarak
implement edilmiştir — **code/test-local-candidate-targeted-green**. Hedefli
kanıt: MASTER + bağımsız reviewer 23/23 test, 72 assertion GREEN; dört JSON
route `auth:sanctum`+`verified`, yalnız POST `throttle:5,1`; paket-hedefli
Pint ve `php -l` GREEN. Dondurulmuş agregat snapshot `0cb9d7...` üzerindeki
ilk bağımsız review **RED** sonucu verdi — tek bir P2 (domain eligibility
policy repository'de yinelenmişti); düzeltme uygun durum değerlerini
`WorkspaceState` predicate'inin arkasına taşıdı; düzeltilmiş kod üzerindeki
taze kapanış review'ı **S1_WP02B_CLOSURE_REVIEW_GREEN**'dir (P0/P1/P2 yok).
Non-blocking bir P3 ertelenmiş kalır: 6 karakterlik slug collision DB-korumalı
ve precheck'lidir, ama gerçek eşzamanlı collision'da retry yerine yakalanmamış
500 döner — blocker değildir, düzeltilmedi. WP02B'nin kendi §3 bütçesinde bir
yerel tam QA sırası **bir kez** çalıştırıldı: implementasyon writer'ı tam PHP
suite 74/74 GREEN ve build GREEN raporladı; `composer validate` yalnız
mevcut license-eksikliği owner kararı yüzünden RED kaldı; repository-genelinde
style gate'leri WP02B production allowlist'i dışındaki mevcut sapmayı
raporladı. P2 düzeltmesinden **sonra** ikinci bir yerel tam suite koşusu
çalıştırılmadı — yalnız targeted 23/23/72 + targeted Pint/`php -l`/kapanış
review'ı düzeltilmiş snapshot için tazedir; bu mikro düzeltme sonrası taze bir
"tam suite toplamı" iddia **edilmez**. CI/full QA, yetkili bir commit/push/
promotion olmadığı için çalıştırılmadı. Persistent developer DB bu paket
kapsamında henüz migrate edilmedi; workspace UI/tarayıcı journey'si yoktur —
iddia yalnız API kodu + izole-test-koşusu seviyesindedir. Durum: **WP02B
code/test-local-candidate-targeted-green**, **public-promotion RED** — aynı
iki P1 owner-kararı blocker'ı (composer license/legal owner kararı;
`AGENTS.md`/`docs/31` public-safe çelişkisi) açık kalır, ayrıntı `docs/34`
§13a.

## 7. Kanonik sahiplik

QA/acceptance/vibecoding disiplininin tek kanonik sahibi bu dosyadır. İzlenebilirlik
matrisinin kendisi `docs/29`'da yaşar; bu dosya yalnız disiplini tanımlar.

## 8. Hız bütçesi işaretçisi (SP-01)

Checkpoint kadansı, hedefli test bütçesi, tam QA bütçesi ve risk şeridi
eşikleri **yalnız** `config/development-speed-budget.json`'da sahiplenilir
ve `scripts/speed-gate` ile deterministik doğrulanır; bu dosya bu sayıları
tekrar etmez (bkz. `scripts/speed-gate docs-scan`). Yukarıdaki §1'deki
"tam QA en fazla iki kere" ve §6'daki targeted-evidence rejimleri, o JSON'un
lane bütçeleriyle tutarlıdır — çelişki halinde JSON kazanır. Rasyonel:
`claude_speeder_report.md`, `codex_speeder_report.md`; işletim kuralı:
`.claude/rules/fast-development.md`.
