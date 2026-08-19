# 18 — Stage 1: MVP

**PLANNING ONLY. Şu an çalıştırılamaz — hiçbir kod yazılmadı, bu bir plandır.**

## Owner özeti

- **once**: Restoran yöneticisi menüsünü kağıt/PDF veya elle güncellenen bir
  sistemle yönetiyor; fiyat değişikliği saatler/günler sürebiliyor; QR basıldıktan
  sonra menü güncellemesi genelde QR'ın yeniden basılmasını gerektiriyor.
- **simdi**: Henüz hiçbir şey yok — bu doküman MVP'nin ne olacağının planıdır.
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
- **kalanEngel**: Hiçbir satır kod yazılmadı; Laravel projesi kurulmadı; veritabanı
  yok; hiçbir entegrasyon (Iyzico dahil) bağlanmadı. Bu stage'in "engeli" —
  teknik değil, **henüz başlanmamış olması**dır.
- **capability_delta**: 0 → tam dikey kritik yol (kayıt→...→fiyat güncelleme
  anlık yansıma).
- **Şu-an-çalıştırılabilir/çalıştırılamaz iddiası**: **Çalıştırılamaz.** Runtime
  yoktur; bu bir plan dokümanıdır.

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
- Performance: `docs/06` Public Menü Performance Budget (JS < 200KB gzip, ilk
  menü JSON < 100KB, p95 < 500ms, LCP < 2.5s — mobil, load test sonrası revize).
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

**Henüz değerlendirilmedi** — bu stage başlamadı. Değerlendirme yalnız gerçek
implementasyon ve kanıt üretildikten sonra yapılabilir.

## Next-stage admission

Post-MVP'ye geçiş, yukarıdaki Acceptance evidence + Metrics eşiklerinin (owner
onaylı hedeflerle) karşılanmasını gerektirir.
