# 18 — Stage 1: MVP

**Stage 1 aktif / implementation-in-progress.** Foundation iskeleti
(S1-WP01A) ve CORE-01-only kimlik/oturum dikey dilimi (S1-WP02A) yerel
olarak çalıştırılabilir durumdadır (bkz. §Owner özeti); tam MVP ve kritik
restoran yolculuğu (tenant/menü/yayın/QR/ödeme uçtan uca) hâlâ **yoktur**
— bu doküman o tam yolun planını taşımaya devam eder.

## Owner özeti

- **once**: Restoran yöneticisi menüsünü kağıt/PDF veya elle güncellenen bir
  sistemle yönetiyor; fiyat değişikliği saatler/günler sürebiliyor; QR basıldıktan
  sonra menü güncellemesi genelde QR'ın yeniden basılmasını gerektiriyor.
- **simdi**: S1-WP01A foundation iskeleti ve S1-WP02A CORE-01-only kimlik/
  oturum dikey dilimi (register→verification-pending→signed/expiring email
  verification→authenticated cookie session→logout) yerel olarak hedefli
  kanıtla çalışır durumdadır (`docs/33` §Final durum); MVP'nin geri kalanı
  (tenant/menü/yayın/QR/ödeme uçtan uca) hâlâ **yoktur** — bu doküman o
  tam yolun planını taşımaya devam eder.
- **fark**: MVP tamamlandığında restoran yöneticisi kayıt olur, restoranını kurar,
  menüsünü girer, yayınlar, QR üretir/basar; müşteri QR'ı okutup güncel menüyü
  görür; yönetici fiyatı değiştirdiğinde müşteri **aynı QR'ı** okutarak yeni
  fiyatı anında görür — QR yeniden basılmaz.
- **kullaniciYolculugu** (somut CRM-benzeri analoji): Bir restoran sahibinin bir
  form doldurup (menü girişi) kaydettiği (yayınlama), formun onaylandığı
  (publish checklist geçtiği) ve sonucun anında görünür olduğu (public menu)
  bir "submit → save → publish → görünür" döngüsü — reddetme/retry karşılığı
  burada "publish failure state + son başarılı sürümü koru"dur (`docs/04`
  Publication modülü).
- **kalanEngel**: S1-WP01A foundation iskeleti (implementation-in-progress —
  Laravel projesi kuruldu, CORE-05 registry/env katmanlama/CI/OWASP ASVS
  baseline'ı hedefli kontrollerle doğrulandı, `docs/26` S1-WP01) üzerine artık
  S1-WP02'nin CORE-01-only alt dilimi **S1-WP02A** (register→
  verification-pending→signed/expiring email verification→authenticated
  cookie session→logout) da yerel hedefli kanıtla eklenmiştir
  (local-candidate-targeted-green, `docs/33` §Final durum). **2026-08-26
  güncellemesi:** kritik restoran yolculuğunun tenant/menü/yayın/QR ekseni
  artık bağlanmış ve kanıtlanmıştır — kayıt→workspace→marka/şube→menü/
  kategori/ürün→yayın→QR→public menü→fiyat değişikliği→yeniden yayın→aynı
  QR'da yeni fiyat zinciri hem gerçek HTTP yüzeyinden otomatik testle
  (`RestaurantCriticalJourneyTest`) hem de tarayıcıda manuel demoyla
  yürütülmüştür. **Ödeme ekseni bu kanıtın dışındadır** ve hâlâ
  bağlanmamıştır. Sayaç **0/8**'de kalır: ürün fazı sayacı özellik değil
  kapanmış Exit Gate sayar ve Exit GO/NO-GO **hâlâ owner kararını
  beklemektedir** (§Exit GO/NO-GO/CONDITIONAL).
- **capability_delta**: ürün kabiliyeti **0 → bounded CORE-01 local
  candidate** (register→verification-pending→email verification→session→
  logout, `docs/33` §Final durum) — bu, restoran işletme kabiliyeti
  **değildir**. İlerleme sayacı bu delta'dan **bağımsız olarak 0/8**'de
  kalır (bkz. §İlerleme, `docs/17` §4). Hedeflenen tam delta hâlâ **tam
  dikey kritik yol**tur (kayıt→...→fiyat güncelleme anlık yansıma);
  S1-WP01A + S1-WP02A yalnız bu yolun üzerine kurulacağı iskeleti ve ilk
  dilimini sağlar.
- **Şu-an-çalıştırılabilir/çalıştırılamaz iddiası**: **Kritik restoran
  yolculuğunun tamamı çalıştırılamaz** — tenant/menü/yayın/QR/ödeme henüz
  yoktur; bu hâlâ büyük ölçüde bir plan dokümanıdır. İki istisna: (1)
  S1-WP01A foundation iskeletinin health-check + foundation-status ekranı,
  hedefli kontrollerde (implementation-in-progress; FULL_QA_LOCAL_1 bir kez
  çalıştı — 8/10, Pint hedefli düzeltildi; Gate 1 yalnız composer license
  metadata/owner kararı eksikliğiyle RED; ikinci tam QA bütçesi yalnız CI
  için rezerve; iki bağımsız review de INDEPENDENT_REVIEW_RED sonucu verdi —
  ikincisi (209 dosyalık dondurulmuş snapshot) iki P2 kapanışını kendi
  hedefli PHPUnit 10/10/34 assertion ve Vitest 7/7 kontrolleriyle GREEN
  doğruladı, üçüncü bir blocker bulmadı; RED yalnız aynı iki P1
  owner-kararının açık kalmasından gelir, bkz. `docs/27` §6) çalıştığı
  doğrulanmıştır; (2) S1-WP02A'nın CORE-01-only dikey dilimi (register→
  verification-pending→signed/expiring email verification→authenticated
  cookie session→logout) yerel olarak hedefli kanıtla çalıştığı
  doğrulanmıştır — **WP02A local-candidate-targeted-green**, **public-
  promotion RED** (`docs/33` §Final durum, `docs/27` §6). Bu iki istisna
  **restoran işletme yolculuğunu** (menü, QR, ödeme, tenant vb.) **ispat
  etmez** — CORE-01 kimlik/oturum dilimi gerçek ve yerel olarak çalışan bir
  kabiliyettir, ama kritik restoran yolculuğunun kendisi değildir.

## Amaç

Tek dikey kritik yolun gerçekten çalışır (runnable) olduğu, kanıtla doğrulanmış
bir ilk sürüm.

## Scope / non-goals

**Scope (MVP'de mutlaka bulunacaklar — `docs/01` §5 ile birebir)**:
- Public site: home, features, pricing, how-it-works, FAQ, contact, login,
  register, terms, privacy, KVKK aydınlatma.
- Authentication: e-posta kayıt, doğrulama, login/logout, şifre sıfırlama,
  davet kabulü, session expiry, rate limiting.
- Tenant/restoran: workspace, bir restoran lokasyonu, restoran bilgileri, logo,
  iletişim, dil, para birimi, timezone, public slug.
- Menu Catalog: bir aktif ana menü, kategori, ürün, ürün görseli, fiyat,
  görünürlük, out-of-stock, sıralama, standart 14 alerjen, preview.
- Publication: draft/preview/publish/snapshot.
- QR Destination + basic QR designer (PNG/SVG export, altı temel tema,
  ISO 216 A4/B4/A5/B5/A6/B6/A7/B7 portrait/landscape, server-side PDF export,
  browser/OS print, scannability/decode doğrulama — `docs/08` §6).
- Bulk QR/table wizard (masa/alan bulk).
- Scan analytics (temel: total/today/7gün/30gün/QR-bazlı).
- Team: Owner + Editor, davet/iptal/kaldırma.
- Billing: plan katalogu + manuel ödeme kaydı + **çalışan** Iyzico **sandbox**
  dikey dilimi (adaptör, sandbox checkout/3DS, server-side tutar doğrulama,
  idempotency, imzalı webhook doğrulama + replay protection, deterministik
  success/failure — hiçbiri canlı para değildir; `docs/09` §6).
- Tenant/security/audit/backup temel operasyonu.

**Non-goals (bu stage'de yok)**: Ordering, reservation, loyalty, CRM
(genişletilmiş), marketing automation, multi-branch, custom domain, AI-destekli
üretim özellikleri, native app.

## Entry gate

Bu külliyatın (docs/00–32, modules/, skills/, templates/) plan-onayı alması.
Owner'ın `docs/16`'daki MVP-kritik açık maddeleri (BIZ-01, OPS-01, QR-01, QR-02,
AUTH-02, DR-01/02, OBS-01, LEG-02, MED-01, A11Y-01, COM-01, AI-02) en azından
"containment ile ilerle" kararıyla kapatması.

## Milestone / WP

Bu stage'in iş paketi kırılımı `docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md`'de
sahiplenilir (burada tekrar edilmez).

## Module increments

CORE-01..08, CORE-10, CORE-12..16 (MVP-required baseline dilimler — tam),
CORE-09 Taxonomy (temel), CORE-11 ECA (yalnız kritik yolun ihtiyaç duyduğu
minimum event/kural sözleşmesi; tam authoring/catalog UX Post-MVP —
`docs/26` §1), Onboarding, Menu Catalog, Publication, QR Destination,
QR Print Export (baseline: PNG/SVG, server-side PDF, bulk wizard, altı temel
tema — `docs/08` §6), Themes/Brand (temel), Page Composition (temel),
Content/Frontpages (temel), Pricing/Subscription/Billing (manuel + Iyzico
sandbox dikey dilimi), Analytics/Consent/Tagging (temel), Mini CRM (yok —
Post-MVP'ye ertelendi, bkz. `docs/19`).

Bu satır tüm CORE bağlamlarının MVP'de özellik-tam olduğunu **iddia etmez** —
kesin kapsam `docs/26` §1 modül×stage matrisinde kanoniktir, burada yalnız
özetlenir.

## Dependency / critical path

```
CORE-01 Identity → CORE-02 Tenancy → Onboarding → Menu Catalog → Publication
  → QR Destination → QR Print Export → (Scan Analytics paralel)
CORE-04 Entitlements ve CORE-12 Money, Pricing/Billing ile paralel geliştirilir
  (kritik yolu bloklamaz, ama Exit Gate'i bloklar).
```

## Acceptance evidence

`docs/27-QA-ACCEPTANCE-VIBECODING.md`'deki genel disipline ek olarak: tek dikey
kritik yolun uçtan uca **gerçekten** çalıştığının kanıtı (E2E test kaydı +
manuel demo kaydı), tenant escape testi (AUTH-02), QR fiziksel scan testi
(QR-02), restore drill (DR-02).

## Metrics

- **Time to First QR** (birincil metrik, hedef < 5 dakika — kaynak dokümandan
  korunmuş hedef, `docs/00` §4).
- Fiyat güncellemesinin müşteriye yansıma süresi (ikincil metrik).
- Activation rate (restoran + menü + kategori + ürün + yayın + ilk QR + QR testi
  tamamlanma oranı).

## Security / a11y / performance / i18n

- Security: OWASP ASVS temel seviye, tenant isolation testi zorunlu.
- A11y: WCAG 2.2 AA hedefi, RTL testi en az bir kritik akışta (login).
  **Durum (2026-08-26):** RTL şartı `tests/Feature/Rtl/CriticalFlowDirectionTest.php`
  ile karşılandı (RTL-LOGIN-DOCUMENT-01, -DERIVED-02, RTL-LTR-UNAFFECTED-03).
  Test yazılırken gerçek bir açık bulundu: yedi auth şablonu ve iki e-posta
  şablonu `<html lang="en">` diyordu ve hiçbirinde `dir` yoktu — yani şartın
  adıyla andığı giriş akışı hiç RTL değildi. Dokuzu da artık yönü
  `DocumentLocale`'den türetir.
- Performance: `docs/06` Public Menü Performance Budget (JS < 200KB gzip, ilk
  menü JSON < 100KB, p95 < 500ms, LCP < 2.5s — mobil, load test sonrası revize).
  **Ölçüm durumu (2026-08-26):** JS bütçesi `resources/js/design-system/bundle-budget.test.ts`
  ile, menü yükü `tests/Feature/Performance/PublicMenuPayloadBudgetTest.php`
  ile ölçülür. 12 kategori × 20 ürünlük gerçekçi bir menü **7,4 KB gzip**
  (273 KB ham) iner; bütçe rahatça tutar. **Bu bütçe sıkıştırmaya bağlıdır:**
  dağıtımda gzip/brotli kapalıysa aynı menü onlarca kat ağırdır ve bütçe
  tutmaz — bu bir dağıtım şartıdır, bir varsayım değil. p95 gecikme ve LCP
  gerçek bir dağıtım ölçümüdür ve **henüz yapılmamıştır**; hiçbir test bunu
  iddia etmez.
- i18n: altı katalog (en/tr/de/fr/ar/ru) scaffold'ı + text-domain wiring +
  PO→MO→JSON pipeline'ın tamamı hazır ve entegre; English kaynak katalog
  complete/default. tr/de/fr/ar/ru içerik-completeness'i (tam çeviri + plural/
  context + RTL görsel completeness) MVP'de zorunlu değildir, Stage 2'ye
  bırakılır (`modules/core-localization.md` §Phase delivery, `docs/19`). RTL
  **altyapısı** (yön/token) MVP'de hazır ve en az bir kritik akışta test edilir.

## Rollback trigger

Kritik yolun herhangi bir adımında veri kaybı veya tenant-escape bulgusu → stage
geri çekilir, Exit Gate ertelenir.

## Exit GO/NO-GO/CONDITIONAL

**Owner kararı bekliyor (2026-08-26 itibarıyla).** §Acceptance evidence'ın
dört kaleminin dördü de artık kayıtlıdır, fakat ikisi kendi beyanıyla
yerel kapsamlıdır. Karar owner'ındır; bu bölüm GO ilan etmez.

| Kanıt | Durum | Kaydın kendi sınırı |
|---|---|---|
| E2E kayıt + manuel demo | **Kapandı** | `RestaurantCriticalJourneyTest` (4/4) modüller arası bileşimi gerçek HTTP yüzeyinden kanıtlar; manuel demo tarayıcıda yürütüldü |
| Tenant escape (AUTH-02) | **Yerel otomatik: passed** | `tenant_isolation_evidence` kaydı: "not an ASVS audit, not a pentest, and not a production proof" |
| QR fiziksel scan (QR-02) | **Kapandı** | Ürünün kendi PNG export'u gerçek telefonla LAN üzerinden okundu; yayınlanmış fiyat (52.50 TRY) doğrulandı. Basılı ölçü/scannability (A4, masa mesafesi) ayrıca ölçülmedi |
| Restore drill (DR-02) | **Yerel: passed** | `backup_restore_evidence` kaydı: RPO/RTO kanıtı değil, production DR tatbikatı değil; 9 satırlık manifest |

RPO/RTO hedefleri owner tarafından belirlenmiştir (**RPO 24 saat, RTO 4 saat**,
`docs/16` DR-01) fakat gerçek hosting'de **ölçülmemiştir**.

### 2026-08-26 günü kapanan mühendislik kalemleri

Yukarıdaki dört kanıt kalemine ek olarak, Stage 1'i açık tutan modül ve
denetim boşlukları aynı gün kapandı. Bunlar Exit kararını owner adına
vermez; kararın hangi zemin üzerinde verileceğini değiştirir.

| Kalem | Durum | Kaydın kendi sınırı |
|---|---|---|
| CORE-12 Money/Ledger | **Kapandı** | Değişmez çift kayıtlı defter; başarılı tahsilat `cash`↔`revenue` yazar, aynı ödeme iki kez bildirilse tek satır olur. Tam reconciliation Stage 3'tür |
| CORE-08 PO→MO→JSON pipeline | **Kapandı** | Altı katalog × altı alan adı derlenir; `en` complete. tr/de/fr/ar/ru **içerik** doluluğu Stage 2'dir |
| OWASP ASVS L1 geçişi | **Kapandı** | Bölüm bölüm kod okuması; üç gerçek açık bulundu ve kapatıldı. Sertifikasyon veya sızma testi **değildir** |
| MED-01 host yetenek probu | **Kapandı** | Ölçüm çalıştırıldığı makine hakkındadır; **hedef sağlayıcı henüz seçilmedi** (owner kararı) |
| Para biçimlendirme (CORE-12 × `docs/13` §4) | **Kapandı** | Beş kopya tek sahibe indi; sabit 100'e bölme kaldırıldı |
| RTL kritik akış testi | **Kapandı** | Test ilk çalıştığında dokuz şablonun yön türetmediğini buldu ve düzeltti. Arapça **görsel** completeness Stage 2'dir |
| Menü yükü bütçesi | **Ölçüldü** | 12×20 menü 7,4 KB gzip. **Sıkıştırmaya bağlıdır**; p95/LCP ölçülmedi |

Owner'ın kararı için geriye kalan tek teknik bilinmeyen **hosting sağlayıcısı
seçimidir**: prob seçilen host'ta çalıştırılmadan shared-host sertifikasyonu
(`docs/16` DEP-01) kapanamaz.

Bu tabloya göre olgun karar şekli **CONDITIONAL-GO**'dur: kritik yol gerçekten
çalışır ve kanıtlıdır, ancak tenant-isolation ile restore kanıtları yerel
kapsamdadır ve shared-host sertifikasyonu (`docs/16` DEP-01) açıktır. Nihai
GO / CONDITIONAL-GO / NO-GO ilanı owner'a aittir ve bu belge onu peşinen
vermez.

## Next-stage admission

Post-MVP'ye geçiş, yukarıdaki Acceptance evidence + Metrics eşiklerinin (owner
onaylı hedeflerle) karşılanmasını gerektirir.
