# 01 — Product Charter & Scope

**PLANNING ONLY.**

## 1. Değer önerisi

> Fiziksel QR kod sabit kalır; QR kodun yönlendirdiği içerik değiştirilebilir.

Restoran yöneticisi QR kodu değil, QR kodun arkasındaki *destination*'ı yönetir.
Bu ayrım ([`docs/08-QR-PRINT-EXPORT.md`](08-QR-PRINT-EXPORT.md)'de teknik olarak
detaylandırılır) tüm ürünün mimari omurgasıdır: QR fiziksel ve kalıcı, menü ve
destination dinamik.

## 2. Ürün felsefesi — "Beauty for everyone"

Öncelik sırası (üstteki alttakini domine eder, ama hiçbiri feda edilmez):

```
usability → accessibility → clarity → consistency → aesthetics → brand
```

Somut anlamı: bir özellik "güzel" ama kullanılamıyorsa reddedilir; erişilebilir
değilse (WCAG 2.2 AA, bkz. `docs/15-SECURITY-PERFORMANCE-SHARED-HOST-MOBILE.md`)
reddedilir; anlaşılır değilse basitleştirilir; tutarsızsa (aynı etkileşim iki
yerde farklı davranıyorsa) düzeltilir; ancak *sonra* estetik ve marka kimliği
üzerinde çalışılır. Bu sıra `docs/06-UX-DESIGN-SYSTEM-THEMES.md` ve
`docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md`'deki kabul kriterlerinin gerekçesidir.

## 3. Değişmez ürün ilkeleri

| İlke | Anlamı | Nerede uygulanır |
|---|---|---|
| Self-service | Restoran yöneticisi teknik destek almadan menü/QR yönetir | Onboarding, panel UX (`docs/02`) |
| Tenant izolasyonu | Bir workspace başka bir workspace'in verisini hiçbir koşulda göremez/değiştiremez | `docs/05-DOMAIN-DATA-TENANCY-AUTH.md` |
| Modülerlik | Core sabit, business modülleri tak-çıkar; disable veri silmez | `docs/04-MODULAR-MONOLITH-CORE-MODULES.md` |
| AI kapalıyken tam determinizm | AI hiçbir kritik iş akışının *tek* yolu olamaz — bu, AI'nın "yokluğunun varsayılan" olduğu anlamına gelmez: deterministik kabiliyet her zaman mevcuttur, AI zenginleştirmesi 62/62 modülde mimari olarak pre-wired'dır ve opt-in'dir (`docs/32` §Temel ilke) | `docs/14-AI-FIRST-MCP-SKILLS.md`, `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` |
| Dynamic QR permanence | Basılı QR asla yeniden basılmayı gerektirmemeli | `docs/08-QR-PRINT-EXPORT.md` |
| Edit / publication ayrımı | Düzenleme verisi ile müşteriye gösterilen veri her zaman ayrı | `docs/04` (Publication modülü) |

## 4. Değer zinciri (gereksinim hiyerarşisi)

```
Product Capability → Module → Feature → Flow → Story → Acceptance → Test
```

Hiçbir user story bu zincirin başından (Product Capability) türetilmeden yazılmaz.
Örnek uygulama: `templates/MODULE-SPEC.md` şablonu bu yedi seviyeyi zorunlu alan
olarak taşır; her `modules/*.md` dosyası bu zincirin tamamını doldurmak zorundadır.

Seviye tanımları:

1. **Product Capability** — geniş iş kabiliyeti (örn. Dijital menü yönetimi).
2. **Module** — kabiliyetin bağımsız sınırı, verisi, kuralları, bağımlılıkları.
3. **Feature** — modül içinde kullanıcıya sunulan somut işlev.
4. **User Flow** — bir hedefe ulaşmak için izlenen adım dizisi.
5. **User Story** — belirli rol × belirli bağlam ihtiyacı.
6. **Acceptance Criteria** — tamamlanmış kabul şartları.
7. **Test Cases** — happy path, edge case, permission, validation, güvenlik, performans.

## 5. Strict MVP sınırı

MVP'ye giren/girmeyen kesin kural: **tek dikey kritik yol gerçekten çalışır
olmadan** hiçbir aşama çıkışı GO alamaz (bkz. `docs/18-STAGE-01-MVP.md` §Entry/Exit
Gate). Kritik yol: kayıt → workspace → restoran → menü → kategori → ürün → yayın →
QR → tarama → fiyat güncelleme → anlık yansıma. Bu yolun dışındaki her şey (ordering,
reservation, loyalty, CRM, marketing automation) `docs/25`'e kadar olan stage'lere
dağıtılmıştır; MVP'de yoktur (bkz. `9. Tak-Çıkar Opsiyonel Modüller` sınıflandırması,
külliyata `docs/04`'te OPT-XX kodlarıyla taşınmıştır).

## 6. Audit / export / operations / plan-entitlement / evidence gates

Bu beş kavram MVP'den itibaren zorunludur, "sonra eklenir" değildir:

- **Audit**: her kritik değişiklik (fiyat, görünürlük, publish, QR destination,
  rol, abonelik durumu) actor/tenant/before/after/timestamp ile loglanır
  (`docs/04` CORE modülü Audit/Event Outbox).
- **Export**: kullanıcı kendi verisini indirebilmeli (KVKK/GDPR temelli zorunluluk,
  `docs/05`).
- **Operations**: queue/scheduler/health-check/backup-restore gün 1'den planlanır
  (`docs/15`).
- **Plan-entitlement**: özellik erişimi yalnız UI'da gizlenmez, backend'de
  zorunlu kılınır (`docs/09-PRICING-BILLING-MONEY-IYZICO.md`).
- **Evidence gates**: her stage çıkışı kanıtister, "vibe says done" kabul edilmez
  (`docs/27-QA-ACCEPTANCE-VIBECODING.md`).

## 7. Kapsam dışı bırakılanlar (açık non-goals)

- Next.js (herhangi bir amaçla) — bkz. `docs/03-ARCHITECTURE-DECISIONS.md` ADR
  ilgili maddesi.
- Docker / container tabanlı varsayılan dağıtım — shared-host default (`docs/15`).
- Filament veya ikinci bir UI stack'i restoran paneli için.
- Kart bilgisi saklama (`docs/09`).
- Native mobil uygulama MVP'de (Capacitor shell yalnız ilerideki stage'de, `docs/22`).

## 8. Kanonik sahiplik

Bu doküman ürün felsefesi, değer zinciri ve strict MVP sınırının **tek kanonik
sahibidir**. Rol/yolculuk detayı `docs/02`'de, mimari kararlar `docs/03`'te,
modül detayları `docs/04`+`modules/`'da yaşar — buradan yalnız link verilir,
içerik tekrar edilmez.
