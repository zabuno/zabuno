# 04 — Modular Monolith & Core Modules

**PLANNING ONLY.** Bu doküman modül kataloğunun **indeksidir**; her modülün tam
spec'i `modules/<module-key>.md` dosyasında `templates/MODULE-SPEC.md` şablonuyla
yazılır. Burada isim listesi bırakılmaz — her satır bir amaç/sınıf/bağımlılık
özetiyle gelir; detay linke gider.

## 1. Modül sınıfları

- **Core (zorunlu, kaldırılamaz)** — ticari modül değildir, devre dışı bırakılamaz.
- **Required product (hedef ürünün zorunlu iş/kabiliyet seti)** — hedeflenen
  Zabuno ürününün tam kapsamında **olacağı önceden kararlaştırılmış**, tak-çıkar
  olmayan iş modülleri. Bu sınıf "MVP'de bulunur" ile eş anlamlı **değildir**:
  her required product modülünün **stage-teslimatı** kademelidir ve tek kanonik
  kaynağı `docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md` §1'deki modül × stage
  matrisidir (örn. Mini CRM ve Helpdesk/Tickets Stage 2'de, SEO/Search &
  Discovery temel kapsamı Stage 3'te, Integration Hub genişletilmiş kapsamı
  Stage 6'da teslim edilir — hiçbiri MVP'nin dikey kritik yoluna dahil değildir,
  bkz. `docs/18` Scope). MVP'nin dikey kritik yolu için gerekli **alt küme**
  ayrıca `docs/18` Scope bölümünde adlandırılır.
- **Optional (tak-çıkar)** — plan/edition'a göre enable edilir, disable veri silmez.

## 2. Core Kernel (CORE-01..16)

| Kod | Modül | Amaç (özet) | Spec |
|---|---|---|---|
| CORE-01 | Identity & Sessions | Kullanıcı, login/register/verify, şifre sıfırlama, oturum | [`modules/core-identity-sessions.md`](../modules/core-identity-sessions.md) |
| CORE-02 | Tenancy/Organization/Venue | Workspace/Brand/Location çözümü, tenant isolation | [`modules/core-tenancy.md`](../modules/core-tenancy.md) |
| CORE-03 | Authorization | RBAC+ABAC+ReBAC PDP, panel/module/record seviye kontrol | [`modules/core-authorization.md`](../modules/core-authorization.md) |
| CORE-04 | Entitlements & Usage Metering | Plan limitleri, modül erişimi, kullanım kotaları | [`modules/core-entitlements.md`](../modules/core-entitlements.md) |
| CORE-05 | Module Registry | Manifest, bağımlılık grafiği, lifecycle | [`modules/core-module-registry.md`](../modules/core-module-registry.md) |
| CORE-06 | Settings/Secrets/Integrations | Platform/tenant/kullanıcı ayar hiyerarşisi, secret yönetimi | [`modules/core-settings-secrets.md`](../modules/core-settings-secrets.md) |
| CORE-07 | Audit/Event Outbox | Kim/ne zaman/ne değişti, cross-module event dağıtımı | [`modules/core-audit-outbox.md`](../modules/core-audit-outbox.md) |
| CORE-08 | Localization | Gettext PO/MO/JSON projeksiyonu, locale yönetimi | [`modules/core-localization.md`](../modules/core-localization.md) |
| CORE-09 | Taxonomy | Esnek vocabulary'ler (typed alanları taxonomy'ye taşımaz) | [`modules/core-taxonomy.md`](../modules/core-taxonomy.md) |
| CORE-10 | Workflow/State Machine | Domain lifecycle motoru (Symfony Workflow adayı) | [`modules/core-workflow-state.md`](../modules/core-workflow-state.md) |
| CORE-11 | ECA Rules | Event-Condition-Action + Automation Studio | [`modules/core-eca-rules.md`](../modules/core-eca-rules.md) |
| CORE-12 | Money/Ledger | brick/money + immutable double-entry ledger | [`modules/core-money-ledger.md`](../modules/core-money-ledger.md) |
| CORE-13 | File/Media | Upload, validate, storage, derivative pipeline | [`modules/core-file-media.md`](../modules/core-file-media.md) |
| CORE-14 | Notifications | E-posta + uygulama içi bildirim altyapısı | [`modules/core-notifications.md`](../modules/core-notifications.md) |
| CORE-15 | Data Lifecycle | Export, archive, soft delete, retention, purge | [`modules/core-data-lifecycle.md`](../modules/core-data-lifecycle.md) |
| CORE-16 | Legal Records | Şartlar/aydınlatma/rıza/doküman versiyonları (KVKK) | [`modules/core-legal-records.md`](../modules/core-legal-records.md) |

Not: eski dokümandaki "Queue and Scheduler", "API Contract", "Security",
"Observability" alanları CORE-01..16 numaralandırmasına yeniden dağıtılmıştır:
Queue/Scheduler → Infrastructure/Adapters katmanında her modülün kullandığı ortak
servis (kendi CORE kodu yok, `docs/15`'te operasyonel olarak ele alınır); API
Contract → `docs/29` ve her modülün "public contracts/events" alanı; Security →
`docs/15`; Observability → `docs/15` §Observability. Bu, orijinal 16 alanı
kaybetmeden Laravel modül registrine (her CORE bir manifest sahibi modül olmalı)
uyarlamaktır — kayıt `docs/16-GAP-UNKNOWN-UNKNOWNS.md`'ye "CORE numaralandırma
yeniden-eşleme" notuyla düşülmüştür.

## 3. Required product modülleri (M0)

| Modül | Amaç (özet) | Spec |
|---|---|---|
| Onboarding | Kayıttan ilk çalışan QR'a yönlendirme | [`modules/onboarding.md`](../modules/onboarding.md) |
| Menu Catalog | Menü/kategori/ürün/MenuItem veri modeli | [`modules/menu-catalog.md`](../modules/menu-catalog.md) |
| Publication | Draft/preview/publish/snapshot ayrımı | [`modules/publication.md`](../modules/publication.md) |
| QR Destination | Stabil token → destination resolver | [`modules/qr-destination.md`](../modules/qr-destination.md) |
| QR Print Export | Basic designer, ISO 216 boyutlar, mPDF export | [`modules/qr-print-export.md`](../modules/qr-print-export.md) |
| Themes/Brand | Marka/tema tokenları, 5 tema domeni | [`modules/themes-brand.md`](../modules/themes-brand.md) |
| Page Composition | Header/footer/navigasyon/component slot yönetimi | [`modules/page-composition.md`](../modules/page-composition.md) |
| Content/Frontpages | Landing/pricing/features/FAQ içerik yönetimi | [`modules/content-frontpages.md`](../modules/content-frontpages.md) |
| Pricing/Subscription/Billing | Plan/entitlement/fatura | [`modules/pricing-subscription-billing.md`](../modules/pricing-subscription-billing.md) |
| Iyzico Payment | Checkout/3DS/webhook adaptörü | [`modules/iyzico-payment.md`](../modules/iyzico-payment.md) |
| Analytics/Consent/Tagging | First-party event ledger, consent-gated tag'ler | [`modules/analytics-consent-tagging.md`](../modules/analytics-consent-tagging.md) |
| SEO/Search & Discovery | Tek birleşik SEO capability map | [`modules/seo-search-discovery.md`](../modules/seo-search-discovery.md) |
| Mini CRM | Contact/consent/timeline/segment | [`modules/mini-crm.md`](../modules/mini-crm.md) |
| Helpdesk/Tickets | Queue/status/SLA/escalation | [`modules/helpdesk-tickets.md`](../modules/helpdesk-tickets.md) |
| AI Platform | Feature×model routing, kill switch, AI Capability Plane runtime-support (bağımsız tak-çıkar modül **değil** — bkz. not) | [`modules/ai-platform.md`](../modules/ai-platform.md) |
| Integration Hub | Webhook/API client kaydı | [`modules/integration-hub.md`](../modules/integration-hub.md) |

**Not — AI Capability Plane**: Tablodaki diğer 14 required product modülünden
(Onboarding…Integration Hub) farklı olarak "AI Platform" kendi başına bir ticari
domain modülü değildir; CORE-01..16'nın yanına eklenen **yatay** bir runtime-
support plane'idir ve stage-teslimatı `docs/26` §1'de "AI Platform (yatay
plane)" satırıyla ayrıca izlenir. "AI Platform" bu tabloda listelenir çünkü
kaldırılabilir/disable edilebilir bir yüzeyi vardır (kill switch), ama diğer
15 required product modülünden farklı olarak **yatay**dır — CORE-01..16'nın
yanına eklenen ortak bir plane'in runtime-support spesifikasyonudur, ticari
bir domain modülü değildir. Provider hesap/bağlantı yönetimi ayrı bir bileşen
spec'idir: [`modules/ai-provider-account-vault.md`](../modules/ai-provider-account-vault.md)
(62. modül, bu tablonun 32 satırının dışında). 61 modülün tamamının AI ile
ilişkisi (`deterministic_baseline` + `ai_posture`) tek kanonik kaynakta
toplanır: [`docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md`](32-AI-CAPABILITY-MANIFEST-MATRIX.md).

## 4. Optional katalog (tak-çıkar)

| Kod | Modül | Hedef seviye |
|---|---|---|
| OPT-01 | Product Variants | M1 |
| OPT-02 | Product Extras/Modifiers | M1 |
| OPT-03 | Multiple Menus | M1 |
| OPT-04 | Multi-language Content | M1 |
| OPT-05 | Advanced QR Designer | M1 |
| OPT-06 | Advanced Analytics | M1 |
| OPT-07 | CSV Import/Export | M1 |
| OPT-08 | Custom Branding | M1 |
| OPT-09 | Custom Domain | M1 |
| OPT-10 | Multi-branch Management | M1 |
| OPT-11 | Scheduled Publishing | M1 |
| OPT-12 | Menu Version Rollback | M1 |
| OPT-13 | POS Integrations | M2 |
| OPT-14 | Online Ordering | M2 |
| OPT-15 | Restaurant Payment | M2 |
| OPT-16 | Reservation | M2 |
| OPT-17 | Loyalty | M2 |
| OPT-18 | CRM (genişletilmiş) | M2 |
| OPT-19 | Marketing Automation | M2 |
| OPT-20 | Campaign Management | M2 |
| OPT-21 | AI Menu Import | M2 |
| OPT-22 | AI Translation | M2 |
| OPT-23 | AI Product Description | M2 |
| OPT-24 | Inventory/Recipes | M2 |
| OPT-25 | Feedback/NPS | M2 |
| OPT-26 | Developer API/Webhooks (genişletilmiş) | M2 |
| OPT-27 | Marketplace | M2+ (Growth) |
| OPT-28 | Metabase Embed | M2+ (Growth) |
| OPT-29 | Native App Shell (Capacitor) | Growth |

Bu katalog M1/M2 seviyelerinin `docs/19` (Post-MVP) ve `docs/22` (Growth) stage
dokümanlarındaki artımlarla eşleştirilmesi `docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md`'nin
sorumluluğundadır — burada yalnız envanter tutulur, zamanlama tekrar edilmez.

## 5. "Core"u sınırsız büyütmeme kuralı

Yeni bir CORE kodu önerisi yalnız şu üç koşulun **tümü** sağlanıyorsa kabul edilir:
(1) devre dışı bırakılması diğer *tüm* modülleri kıracak kadar temel, (2) ticari
bir modül olarak paketlenemeyecek kadar altyapısal, (3) en az iki farklı required
modülün ortak bağımlılığı. Aksi halde "required product" veya "optional" katalogda
kalır. Bu kural ihlal edilirse `docs/16`'ya gerekçeli bir karar kaydı açılmadan
CORE-17+ eklenemez.

## 6. Bağımlılık grafiği ve semantic compatibility

Her modül manifestinde bildirilen bağımlılıklar `docs/29-TRACEABILITY-MATRIX.md`'de
görselleştirilen (metin tablosu olarak) bir DAG oluşturur. Semantic versioning
(major.minor.patch) modül manifestinde zorunludur; bir modülün major sürüm atlaması
bağımlı modüllerin uyumluluk kontrolünden geçmeden enable edilemez. Bu mekanizmanın
uygulama detayı (hangi paket, hangi resolver) henüz seçilmemiştir —
`docs/16` içinde açık karar maddesi olarak durur.

## 7. Kanonik sahiplik

Modül **listesi ve sınıflandırması** burada kanoniktir. Modül **iç detayı**
(contracts, entitlement, ECA hook, states, acceptance) yalnız `modules/*.md`
dosyalarında yaşar; bu doküman onları tekrar etmez.
