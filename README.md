# Zabuno — Enterprise Plan Külliyatı

> **DURUM: plan külliyatı + implementation-in-progress bir Stage 1 runtime
> foundation.** Bu depo (`zabuno/zabuno`, public), 35 dosyalık bir doküman/
> araştırma korpusunun **yanında**, artık bir Laravel 13 + React 19 tek-
> deployment modular-monolith **foundation** iskeleti de içerir (S1-WP01A,
> `docs/26` S1-WP01) — kurulu `composer.lock`/`package-lock.json`
> bağımlılıkları, ve hedefli kontrollerle (preflight/PHPUnit/Vitest/build)
> doğrulanmış bir `/up` health route'u ile erişilebilir bir foundation-status
> React ekranı mevcuttur. Bu paket **implementation-in-progress**tir:
> FULL_QA_LOCAL_1 bir kez çalıştı (8/10 GREEN; Gate 3 Pint hedefli
> düzeltmeyle GREEN yapıldı, Gate 1 `composer validate --strict` yalnız
> license metadata/owner kararı eksikliğiyle RED kalır, bkz. §Çalıştırma);
> ikinci tam QA bütçesi yalnız sonraki CI/full QA koşusu için rezervedir
> (ikinci bir yerel tam koşu planlanmaz). İki bağımsız review de çalıştı ve
> ikisi de INDEPENDENT_REVIEW_RED sonucu verdi: ilki iki P1 owner-kararı
> blocker'ı (composer license metadata + AGENTS.md/docs/31 public-governance
> çelişkisi) ve hedefli RED→GREEN ile düzeltilmiş iki P2 bulgu tespit etti;
> dondurulmuş snapshot
> `aaf247029d4571fd5347ee24a20d1ffd09a104992103d8f68ad3ca3e7a6a2564`
> (209 dosya) üzerindeki ikinci review bu iki P2 kapanışını kendi hedefli
> kontrolleriyle GREEN doğruladı (PHPUnit 10/10/34 assertion, Vitest 7/7)
> ve üçüncü bir blocker tespit etmedi — RED sonucu yalnız aynı iki P1
> owner kararının hâlâ açık olmasından gelir (bkz. §Çalıştırma). Bu iki
> review tamamlanmış olması "implemented"/"acceptance"/"complete"
> anlamına gelmez. Bu, S1-WP01A'nın kendisinin **hiçbir ürün/iş
> kabiliyetinin** (menü, QR, ödeme, tenant vb.) çalıştığı anlamına
> **gelmediği** anlamına gelir — S1-WP01A'nın dondurulmuş kapsamı yalnız
> CORE-05 modül registry bootstrap'ı, env/config katmanlama, temel CI
> iskeleti ve OWASP ASVS temel checklist'idir. Bounded runtime istisnaları
> çoğuldur — **S1-WP02A** ve **S1-WP02B**'dir (aşağıda): geri kalan tüm
> ürün kabiliyetleri (menü, QR, ödeme, CORE-02 remainder) ve kritik
> menü→yayın→QR→fiyat güncelleme restoran yolculuğu hâlâ **yoktur**. Geri
> kalan 35 dosyalık plan korpusundan `docs/33` ve `docs/34`'ün runtime-kanıt
> bölümleri dışındaki kısım hâlâ **PLANNING ONLY**'dir ve ilerleme sayacı
> hâlâ **0/8**'dir
> (bkz. §İlerleme). Ayrıca S1-WP02'nin CORE-01-only alt dilimi
> **S1-WP02A** (register→verification-pending→signed/expiring email
> verification→authenticated cookie session→logout) artık **yerel
> çalıştırılabilir bir implementation candidate**'tır
> (`http://127.0.0.1:8787/register`, `http://127.0.0.1:8787/login`),
> hedefli kanıtla desteklenir (bkz. `docs/33` §Final durum) — durum
> **WP02A local-candidate-targeted-green, public-promotion RED**'dir; bu
> bir AI-runtime/API bağımlılığı **eklemez**. S1-WP02'nin bounded CORE-02
> alt dilimi **S1-WP02B** (workspace create+owner-membership, üyelik-scope'lu
> liste, current/switch context, enumeration-safe tenant escape reddi) artık
> **code/test-local-candidate-targeted-green**'dir: 23/23 hedefli test/72
> assertion GREEN, bağımsız kapanış review'ı GREEN; bu yalnız API kodu +
> izole test koşusu kanıtıdır — persistent DB migrate edilmedi, hiçbir
> workspace UI/tarayıcı journey'si yoktur (bkz. `docs/34` §13a). Kimlik/tenant
> kabiliyeti artık tamamen yok değildir, ama yalnız bu iki bounded local
> candidate ile sınırlıdır; kritik menü→yayın→QR→fiyat güncelleme yolculuğu
> hâlâ **yoktur**. S1-WP02'nin geri kalanı (CORE-02 remainder, CORE-03,
> CORE-06 + admin shell) hâlâ not-started olduğu için S1-WP02
> bütünü **in-progress**'tir.
>
> **Ürün adı: Zabuno.** Bu külliyatın bir kısmı, tarihsel olarak ayrı bir dış
> arşivde tutulan **legacy** bir QR-menü projesinin/denemesinden süzülmüş
> ürün felsefesi/journey/iş kuralı dersleri taşır — o legacy projenin eski
> adı, owner talimatı gereği bu külliyatın hiçbir yerinde — tarihsel bağlamda
> bile — yazılmaz (`docs/30` postmortem, `docs/31` §7); yeni mimari/
> isimlendirme kararlarına taşınmamıştır. Bu depo, tamamlandığında değil —
> **şu an itibarıyla zaten** — public GitHub deposu **`zabuno/zabuno`**'nun
> kökü olarak yayındadır (`docs/31-PUBLIC-REPOSITORY-GOVERNANCE.md`).

## ⚠️ Depo dışı tasarım külliyatı — devralan ekip mutlaka okusun

Zabuno'nun tasarım yaklaşımı bu depoda **sentez** hâlindedir (`docs/06`,
`docs/35`, `docs/03`, `resources/css/app.css`). Ayrıntılı külliyat ve **çalışan
bir referans implementasyonu** — tam DTCG token pipeline'ı (Figma/AntD/ECharts
çıktılarıyla), foundations CSS katmanları, AEP renderer pattern'leri ve dalga
testleri — deponun **dışında**, owner makinesinde yaşar ve bir `git clone` ile
gelmez.

**Kanonik kayıt: [`docs/36-EXTERNAL-DESIGN-CORPUS.md`](docs/36-EXTERNAL-DESIGN-CORPUS.md)**
— ne olduğu, nerede durduğu, hangi kararları dondurduğu ve devir/exit sırasında
neyin ayrıca aktarılması gerektiği.

UI, tasarım sistemi, token veya Storybook konusunda çalışacaksanız **önce onu
okuyun.** 2026-08-26'da bu külliyatın varlığı fark edilmeden token katmanı
sıfırdan kurulmaya başlandı; bu bölüm o körlüğün tekrarını engellemek içindir.

## Ana yol haritası — buradan başlayın

**[`docs/17-WATERFALL-LIFECYCLE-MASTER.md`](docs/17-WATERFALL-LIFECYCLE-MASTER.md)**
— sekiz aşamalı sabit sıranın **master/navigasyon görünümü**: her stage'in
amacı, entry/exit gate özeti ve **38 WP'nin kısa, sıralı indeksi**. Sıra:

```
MVP → Post-MVP → Go-to-Market → Product-Market Fit → Growth →
Enterprise Level → Maturity Level → Exit Ready
```

WP dağılımı (stage başına, toplam 38): S1=7, S2=6, S3=4, S4=4, S5=4, S6=3,
S7=4, S8=6. Kanonik sahiplik üç ayrı dosyaya bölünür, birbirini tekrar etmez:
**`docs/17`** yalnız yukarıdaki master/navigasyon görünümünü taşır;
**[`docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md`](docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md)**
her WP'nin kimliği/sırası/outcome/scope/predecessor/owner/acceptance-evidence
bağı/status ayrıntısının tek kanonik sahibidir; **`docs/18`–`docs/25`**
(stage-detail dokümanları) her stage'in journey/acceptance/stage
ayrıntılarının tek kanonik sahibidir.

## İlerleme

```
0/8 tamamlandı, 1/8 aktif: Stage 1 — MVP (S1-WP01 Foundation & preflight,
alt paket S1-WP01A implementation-in-progress — bkz. §Çalıştırma;
FULL_QA_LOCAL_1 bir kez çalıştı (8/10, Pint hedefli düzeltildi); ikinci
tam QA bütçesi yalnız CI için rezerve; iki bağımsız review de
INDEPENDENT_REVIEW_RED sonucu verdi — ikincisi (209 dosyalık dondurulmuş
snapshot) iki P2 kapanışını GREEN doğruladı, aynı iki P1 owner-kararı
blocker'ı hâlâ açık; S1-WP01 in-progress, S1-WP02 alt paketler S1-WP02A
local-candidate-targeted-green ve S1-WP02B code/test-local-candidate-
targeted-green ile in-progress, S1-WP03..07 henüz
not-started, docs/26)
```

S1-WP02'nin bir alt dilimi olan **S1-WP02A** (CORE-01-only register→
verification-pending→signed/expiring email verification→authenticated cookie
session→logout dikey dilimi) artık **yerel çalıştırılabilir bir implementation
candidate**'tır: `docs/33`'teki kapsam/threat-model/blind-RED-test-matrisi
sözleşmesi bu dilim için hedefli kanıtla kısmen GREEN'e taşınmıştır (kayıt→
verification-pending→imzalı/süreli link ile doğrulama→stateful cookie
session→logout uçtan uca hedefli testlerle kanıtlıdır; ayrıntı `docs/33`
§Final durum). Durum etiketi net ayrılır: **WP02A local-candidate-targeted-
green**, **public-promotion RED**.

S1-WP02'nin bounded CORE-02 alt dilimi **S1-WP02B** (workspace create+
owner-membership tek transaction, üyelik-scope'lu liste, current/switch
context, enumeration-safe tenant escape reddi) artık **code/test-local-
candidate-targeted-green**'dir: 23/23 hedefli test/72 assertion GREEN,
düzeltilmiş kod üzerinde bağımsız kapanış review'ı GREEN (ayrıntı `docs/34`
§13a). Bu yalnız API kodu + izole test koşusu kanıtıdır — persistent
developer DB bu paket kapsamında henüz migrate edilmedi ve hiçbir workspace
UI/tarayıcı journey'si yoktur; görünür bir localhost workspace ekranı veya
manuel gerçek-hesap E2E iddiası **yapılmaz**. Durum etiketi: **WP02B
code/test-local-candidate-targeted-green**, **public-promotion RED**.

S1-WP02'nin geri kalanı (CORE-02 remainder — state geçişleri, davet,
Brand/Location/işletme profili —, CORE-03 Authorization, CORE-06
Settings/Secrets, admin panel iskeleti) bu iki bounded dilimin **dışındadır**
ve hâlâ not-started'tır — bu yüzden S1-WP02 bütünüyle
**in-progress**'tir, tamamlanmış değildir. Bu, yukarıdaki 0/8 sayacını
**değiştirmez**; public Git promotion iki ayrı, aynı kalan owner/yönetişim
blocker'ı yüzünden RED kalır — (1) composer license/legal owner kararı, (2)
managed `AGENTS.md` yetki bloğu ile `docs/31` public-safe yasağı arasındaki
çelişki — bu iki blocker **yerel bir runtime hatası değildir**, owner/
yönetişim kararı bekler (bkz. `docs/33` §Final durum, `docs/34` §13a,
`docs/27` §6).

Bu plan korpusunun üretilmiş/genişletilmiş olması, S1-WP01A foundation
iskeletinin hedefli kontrollerle doğrulanmış olması dahil, **hiçbir aşamayı
tamamlamaz** —
sayaç yalnız Stage 1'in Exit Gate'i kanıtla GO aldığında artar (`docs/17`
§4). MVP'nin tek dikey kritik yolu (kayıt→menü→yayın→QR→fiyat güncellemesi)
henüz **yoktur**; foundation yalnız o yolun üzerine kurulacağı iskeleti
sağlar.

Kritik ayrım: **Enterprise sınıfı waterfall yönetişimi** (dokümantasyon
disiplini, ADR'ler, requirements→acceptance→test izlenebilirliği) ilk günden
geçerlidir; ayrı bir kavram olan **Stage 6 "Enterprise Level"**
([`docs/23-STAGE-06-ENTERPRISE.md`](docs/23-STAGE-06-ENTERPRISE.md)) ise
SSO/SCIM/data-residency gibi çok daha sonraki bir ürün/operasyon kabiliyet
seviyesidir. Bu iki kavram bu külliyatın hiçbir yerinde birbirine karıştırılmaz.

## Bu korpus nedir, ne değildir

| Bu korpus... | ...değildir |
|---|---|
| Modül-modül, faz-faz, milestone-milestone bir plan külliyatı + S1-WP01A foundation iskeleti + bounded WP02A/WP02B kimlik/tenant dilimleri | Tamamlanmış, uçtan uca bir SaaS / menü→yayın→QR→fiyat güncelleme kritik yolculuğu |
| Mimari kararlar (ADR) ve gerekçeleri | Tamamlanmış bir entegrasyon seti |
| Gap / unknown-unknown analiz kayıtları | Nihai, değişmez bir spesifikasyon |
| Eski projeden alınmış ürün felsefesi/journey/iş kuralı dersleri | Eski teknoloji seçimlerinin yeni karar olarak taşınması |
| Upstream araştırma anlık görüntüsü (provenance kayıtlı) | Herhangi bir upstream kodun portlanmış hali |

## Nasıl gezinilir

1. **Başlangıç noktası — kapsam ve felsefe**: [`docs/01-PRODUCT-CHARTER-SCOPE.md`](docs/01-PRODUCT-CHARTER-SCOPE.md)
2. **Mimari kararlar**: [`docs/03-ARCHITECTURE-DECISIONS.md`](docs/03-ARCHITECTURE-DECISIONS.md)
3. **Modül kataloğu**: [`docs/04-MODULAR-MONOLITH-CORE-MODULES.md`](docs/04-MODULAR-MONOLITH-CORE-MODULES.md) → tek tek [`modules/`](modules/)
4. **Waterfall aşamaları (ana yol haritası)**: [`docs/17-WATERFALL-LIFECYCLE-MASTER.md`](docs/17-WATERFALL-LIFECYCLE-MASTER.md) → `docs/18`…`docs/25`
5. **Bilinmeyenler**: [`docs/16-GAP-UNKNOWN-UNKNOWNS.md`](docs/16-GAP-UNKNOWN-UNKNOWNS.md)
6. **Kaynaklar**: [`docs/28-SOURCE-REGISTER.md`](docs/28-SOURCE-REGISTER.md)
7. **İzlenebilirlik**: [`docs/29-TRACEABILITY-MATRIX.md`](docs/29-TRACEABILITY-MATRIX.md) — kullanıcı talebinin her maddesi buradan doğrulanabilir.
8. **AI Capability Plane**: [`docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md`](docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md) → [`modules/ai-platform.md`](modules/ai-platform.md) + [`modules/ai-provider-account-vault.md`](modules/ai-provider-account-vault.md)
9. **Vibecoding postmortem**: [`docs/30-VIBECODING-POSTMORTEM-SUCCESS-MODEL.md`](docs/30-VIBECODING-POSTMORTEM-SUCCESS-MODEL.md)
10. **Public repo yönetişimi**: [`docs/31-PUBLIC-REPOSITORY-GOVERNANCE.md`](docs/31-PUBLIC-REPOSITORY-GOVERNANCE.md)
11. **S1-WP02A kimlik/oturum dikey dilimi (local-candidate-targeted-green, public-promotion RED)**: [`docs/33-S1-WP02A-IDENTITY-SESSIONS-DELIVERY-CONTRACT.md`](docs/33-S1-WP02A-IDENTITY-SESSIONS-DELIVERY-CONTRACT.md)
12. **S1-WP02B tenancy izolasyonu dikey dilimi (code/test-local-candidate-targeted-green, public-promotion RED)**: [`docs/34-S1-WP02B-TENANCY-ISOLATION-DELIVERY-CONTRACT.md`](docs/34-S1-WP02B-TENANCY-ISOLATION-DELIVERY-CONTRACT.md)

## Dizin yapısı (güncel — bu deponun kendi kökü)

```
zabuno/                          ← bu deponun kökü (public zabuno/zabuno)
├── README.md                    ← bu dosya (kanonik indeks)
├── AGENTS.md                    ← AI/insan katkı sağlayıcıları için çalışma kuralları
├── CLAUDE.md                    ← Claude'a özel yazım/kapsam kuralları
├── .gitignore                   ← public zabuno/zabuno .gitignore sözleşmesi (docs/31 §5)
├── docs/                        ← 00–34 numaralı kanonik doküman seti (35 dosya)
├── modules/                     ← 62 modül, her biri MODULE-SPEC.md + AI Capability Manifest
├── skills/                      ← 22 skill planı (18 orijinal + 4 AI Capability Plane)
├── templates/                   ← MODULE-SPEC / ADR / MILESTONE-GATE / SKILL-SPEC / AI-CAPABILITY-MANIFEST şablonları
├── research/upstream/           ← dış kaynak anlık görüntüleri + UPSTREAM.md provenance
├── evidence/                    ← Part A arşivleme kanıtları (git/stat/verification, yalnız yerel) + public PUBLIC-ARCHIVE-ATTESTATION.md
├── security/                    ← OWASP-ASVS-BASELINE.md (S1-WP01A foundational checklist, docs/26 S1-WP01)
├── tests/                       ← preflight/ (dependency-free RED gate) + Unit/Feature (PHPUnit)
├── app/, config/, routes/,      ← Laravel 13 tek-deployment modular-monolith foundation kaynağı
│   bootstrap/, database/,          (S1-WP01A, docs/03 ADR-L01/L02; app/Domain framework-free Onion
│   public/, storage/                katmanı, config/core-modules.php CORE-01..16 registry)
├── resources/                   ← css/ (Tailwind v4) + js/ (React 19 + TS, Flowbite-first + source-owned
│                                    shadcn Separator adapter, docs/03 ADR-L06)
├── composer.json/.lock          ← PHP ^8.3, Laravel ^13.0 (reproducible lock)
├── package.json/-lock.json      ← Node/npm tarafı (reproducible lock)
├── .env.example/.staging.example/.production.example  ← dev/staging/prod katmanlama (docs/26 S1-WP01)
└── .github/workflows/ci.yml     ← non-Docker build/lint/test iskeleti
```

Bu depoda güncel kökte bir `old/` dizini **yoktur**. Bu külliyatın süzüldüğü
tarihsel legacy QR-menü projesi/denemesi tamamen bu deponun **dışında**, ayrı
bir dış arşivde tutulur — bu depodan o arşive hiçbir Git ilişkisi yoktur
(`docs/00` §6, `AGENTS.md` §6a). `worktrees/` (varsa) yalnız standart Git
worktree mekanizmasının çalışma kopyalarını ifade eder, ayrı bir arşiv
değildir; bu nedenle `.gitignore`'da dışlanır (public depoya dahil edilmez)
ama bir "eski proje köküyle" karıştırılmaz.

## Çalıştırma (S1-WP01A foundation — yalnız iskelet, ürün özelliği yok)

Bu komutlar yalnız foundation iskeletini (health check + erişilebilir
foundation-status React ekranı) çalıştırır; hiçbir restoran/menü/QR/ödeme
akışı içermez:

```
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build   # veya geliştirme için: npm run dev
php artisan serve
```

`php tests/preflight/s1-wp01a-foundation-preflight.php` bağımsız, framework-
free bir RED/GREEN gate'tir (hedefli kontrolde 17/17, `tests/preflight/`).
PHP test paketi `php artisan test`, JS/TS paketi `npm test`, JS/TS lint
`npm run lint` ile çalışır. Bu paket implementation-in-progress'tir:
FULL_QA_LOCAL_1 bir kez çalıştı — 8/10 GREEN; Gate 1 (`composer validate
--strict`) license metadata eksikliği uyarısıyla exit 1 verdi, Gate 3
(Pint) stil sapmasıyla fail oldu. Sonraki hedefli (targeted, tam QA değil)
düzeltme Gate 3'ü mevcut snapshot'ta GREEN yaptı (4 dosya
`vendor/bin/pint --test` green; 8 test/94 assertion + preflight 17/17
green); bu targeted düzeltme ikinci bir tam yerel FULL_QA koşusu değildir
— ikinci tam QA bütçe kalemi yalnız sonraki CI/full QA koşusu için
rezervedir, ikinci bir yerel tam koşu planlanmaz. Gate 1 (`composer
validate --strict`) yalnız composer strict license metadata/owner kararı
eksikliğiyle RED kalır (license field yok; ayrıntı aşağıda).
`composer audit --locked` ve `npm audit --audit-level=high` local
dependency audit kanıtı olarak advisories/0 high+ ile green'dir — bu bir
ASVS veya genel security audit **değildir** (bkz.
`security/OWASP-ASVS-BASELINE.md`). Tarayıcı QA (manuel, desktop
1280x720 + mobile 390x844): overflow yok, console hatası yok; semantic
`nav`/`main`/`h1`/`lang=en` ve sayaçlar doğru — bu bir ürün özelliği
iddiası değildir. FULL_QA_LOCAL_1/Pint hedefli düzeltmesinden sonra ayrı
bir MVC/OOP gap RED→GREEN yapıldı: GET '/' route'u closure yerine final,
`strict_types=1`, public `__invoke` taşıyan `FoundationStatusController`'a
bağlandı; hedefli `FoundationStatusDeliveryArchitectureTest` +
`HealthCheckRouteTest` 6/6 test, 16 assertion GREEN'dir. Bu targeted
düzeltme için tam local QA tekrar çalıştırılmadı — ikinci tam QA bütçe
kalemi yalnız sonraki CI/full QA koşusu için rezervedir. Bu paketin kendi
FULL_QA/handoff durumu implementasyon raporunun sorumluluğudur, burada
tekrar edilmez. İlk bağımsız review çalıştı ve INDEPENDENT_REVIEW_RED
sonucu verdi: iki P1 owner-kararı blocker'ı tespit etti — (1) composer
strict license metadata/owner kararı (license field yok, yukarıda), (2)
public-governance çelişkisi: `AGENTS.md`'deki managed Pane session/panel
detay bloğu `docs/31`'in public-safe yasağıyla çelişir; bu blok bu
paketten önce vardır ve owner istemeden kaldırılmaz, bu paket onu
değiştirmez (ileriye dönük kaldırma da public Git geçmişini silmez). Aynı
review iki P2 bulgu da tespit etti, ikisi de hedefli RED→GREEN kanıtla
düzeltildi: ADR-L03 `strict_types`/default-final uygulama sınırı (abstract
extension point istisnasıyla, `StrictOopApplicationBoundaryTest` GREEN) ve
core module badge numaratörünün `config('core-modules')`'tan controller →
Blade data attribute → React required prop zincirinden türetilmesi
(hardcoded `CORE_MODULE_COUNT` numaratör kaldırıldı; hedefli MVC/health ve
AppShell testleri GREEN).

P2 düzeltmelerinden sonra, dondurulmuş snapshot
`aaf247029d4571fd5347ee24a20d1ffd09a104992103d8f68ad3ca3e7a6a2564` (209
dosya) üzerinde ikinci, bağımsız bir review tamamlandı ve yine
INDEPENDENT_REVIEW_RED sonucu verdi. İkinci review her iki P2 kapanışını
da kendi hedefli kontrolleriyle GREEN doğruladı (reviewer'ın hedefli
PHPUnit koşusu 10/10 test/34 assertion GREEN; hedefli Vitest koşusu 7/7
GREEN) ve üçüncü bir owner-kararı blocker'ı tespit etmedi — RED sonucu
yalnız yukarıdaki aynı iki P1 blocker'ın hâlâ açık olmasından gelir. P2
sonrası ayrı, hedefli bir `npm run build` koşusu da GREEN'dir (Vite
8.2.1, 257 modül, exit 0; bu bir tam local QA koşusu değildir, §3
bütçesindeki ikinci tam QA kalemini harcamaz). Bu build üzerinde yapılan
masaüstü tarayıcı smoke testi de GREEN'dir: HTTP 200, registry-türevi
sayaçlar 16/16 ve 0/8 doğru, semantic `nav`/`main`/`h1`/`lang=en` mevcut,
yatay overflow yok. Durum implementation-in-progress / promotion RED /
yayın yok olarak kalır — yayın, yalnız yukarıdaki iki P1 owner kararı
kapanana kadar RED'dir.

## Kaynak dokümanlar (bu korpusun girdisi)

- Codex ana kapsam metni — orkestratör oturum eki üzerinden okundu (dosya
  adı/UUID public dokümanda taşınmaz, `docs/31` §7)
- Tarihsel arşivleme öncesi (pre-publication) kök `AGENTS.md` (eski QR Menü
  SaaS kapsam dokümanı) — **[legacy, dış arşivde]**, bu depoda karşılığı yok
- Tarihsel arşivleme öncesi kök `CLAUDE.md` (legacy QR-menü projesinin Django
  tabanlı denemesine ait referans dokümanı) — **[legacy, dış arşivde]**, yeni
  ürün adı Zabuno'dur

Donmuş kapsam kanıtları (kaynak metin SHA-256 değerleri, arşivleme öncesi kök
HEAD taahhüdü) ve sekiz aşamalı sabit sıra, görevi veren Codex Desktop MASTER
talimatında verildiği şekliyle doğrulanmıştır; bu değerlerin kendisi yalnız
yerel ham kanıt kayıtlarında (`evidence/`, yalnız yerel — public depoya
taşınmaz) tutulur, bu public doküman değerleri tekrar basmaz.

## Çalıştırılabilirlik iddiası

**Hiçbir modül spec'i, hiçbir stage dokümanı ürün/iş kabiliyeti için "şu an
çalıştırılabilir" iddiası taşımaz.** Her stage dokümanı açıkça "şu an
çalıştırılamaz / runtime yok" notunu taşır (bkz. `docs/18`…`docs/25`, alan:
*şu-an-çalıştırılabilir/çalıştırılamaz iddiası*) — bu değişmemiştir. Üç
bounded istisna vardır, her biri kendi kanıt seviyesinde: (1) S1-WP01A
foundation iskeleti — `/up` health route'u ve foundation-status React
ekranı **hedefli kontrollerde çalıştığı doğrulanmış** implementation-in-
progress bir pakettir (bkz. §Çalıştırma; FULL_QA_LOCAL_1 bir kez çalıştı —
8/10, Pint hedefli düzeltildi; ikinci tam QA bütçesi yalnız CI için
rezerve; iki bağımsız review de INDEPENDENT_REVIEW_RED sonucu verdi —
ikincisi (209 dosyalık dondurulmuş snapshot) iki P2 kapanışını GREEN
doğruladı ve üçüncü bir blocker bulmadı, aynı iki P1 owner-kararı hâlâ
açık); (2) S1-WP02A kimlik/oturum dikey dilimi — `/register` ve `/login`
yerel olarak çalışır, hedefli test/review kanıtıyla desteklenir (`docs/33`
§Final durum); (3) S1-WP02B tenancy baseline — yalnız API kodu + izole
hedefli test koşusu kanıtı (23/23 test/72 assertion GREEN), persistent DB
migrate edilmedi, workspace UI/tarayıcı journey'si yoktur (`docs/34`
§13a). Bu üç istisna da tam bir kabul/acceptance/complete iddiası
**değildir** ve tek başına kritik dikey yolun
(kayıt→menü→yayın→QR→fiyat güncellemesi) çalıştığı anlamına **gelmez** —
o yol henüz **yoktur**. Bu külliyat
tamamlandığında bile — yani tüm 35 `docs/` dosyası, 62 modül ve 22 skill
planı yazıldığında bile — ürün hâlâ **0/8**'dedir; plan üretimi (S1-WP01A
foundation iskeleti dahil) bir MVP teslimatı **değildir**, Stage 1'in Exit
Gate'i kanıtla GO almadan sayaç artmaz. AI
Capability Plane'in (`docs/32`) mimari olarak Stage 0'dan itibaren pre-wired
olması da bu sayacı **değiştirmez** — mimari hazırlık, çalışan bir ürün
implementasyonu değildir.
