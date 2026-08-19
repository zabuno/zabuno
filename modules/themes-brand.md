# Themes / Brand

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Marka görünümü (logo, renk, tipografi) ve 5 tema domeninin token/layout
yönetimini sağlamak.

## Brand asset ve renk sözleşmesi

- **Asset slotları** (sahiplik burada; upload/derivative pipeline `docs/07`
  §6'da genişletilir): logo, cover, profile/avatar (external/social paylaşım
  için), favicon, app icon, Open Graph (OG) share image.
- **Renk rolleri**: primary, secondary, accent, neutral — her rol bir design
  token'a bağlanır; tema domenleri arası paylaşılan token seti (`ADR-L09`,
  `docs/03`).
- **Tipografi**: heading/body/mono font aileleri, en az bir fallback stack.
- **Kalite kapısı**: safe contrast (WCAG 2.2 AA otomatik kontrol,
  aşağıdaki ## Accessibility / i18n) + draft → preview → publish → rollback
  döngüsü (## States) her renk/asset değişikliği için zorunludur — doğrudan
  canlıya yazma yoktur.

## Bounded context
Tema token yönetimi. Sayfa içeriği Content/Frontpages'in, sayfa iskeleti Page
Composition'ın sorumluluğunda.

## Owner
Design + Engineering.

## Sınıf
Required product.

## Bağımlılıklar
CORE-13 (logo/marka görseli).

## Public contracts / events
`ThemePort::tokens(domain, tenant)`; `ThemePublished` event'i.

## Tenant isolation
Marka tokenları tenant-scoped; platform tema (storefront/superadmin) platform
geneli.

## Permissions
`theme.manage`.

## Entitlement / quota
Custom Branding (OPT-08) plana bağlı.

## ECA hooks
Yok.

## AI-off / AI-on davranışı
AI'dan bağımsız (AI destekli tema önerisi ileri özellik, opsiyonel).

## UX one-click journey
Renk paleti seçiminde tek tık canlı önizleme.

## States
Tema: `draft → preview → published → rolled_back`.

## Data retention / export
Önceki tema versiyonları rollback için saklanır.

## Observability
Tema publish sıklığı.

## Security / privacy
Yok.

## Accessibility / i18n
Renk kontrastı WCAG 2.2 AA otomatik kontrolü (yayınlama öncesi kalite kapısı).

## Phase delivery
Stage 1 MVP — temel marka görünümü; Stage 5'te Custom Branding (OPT-08) tam
kapsam.

## Acceptance
Kontrast kalite kapısının düşük kontrastlı bir paleti reddettiğinin testi.

## Rollback
Disable edilemez (required product) ama tema rollback'i kendi başına bir
özelliktir (draft/preview/publish/rollback döngüsü).

## Open questions
Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Marka renk paleti/tema token önerisi (logo/marka rengine göre)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Marka varlıkları (logo/renk — genel işletme verisi)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Tema uygulama/yayına alma kararı insan onayı gerektirir
- **Human approval**: Üretilen her AI çıktısı (taslak/öneri/açıklama) kalıcı
  veri veya eylem haline gelmeden önce ayrı, açık bir insan eylemi gerektirir
  (`docs/01` §3, `docs/06` §7).
- **Feature policy**: feature × provider/model × account × policy ×
  tenant/residency `modules/ai-provider-account-vault.md` üzerinden çözülür.
- **Budget/credit behavior**: reserve→invoke→debit/reconcile/release/refund
  (`modules/ai-provider-account-vault.md` §credit ledger); kullanılmayan/
  reddedilen öneri release/refund edilir.
- **Eval/audit**: Kullanım/kabul oranı ve çağrı audit'i CORE-07'ye yazılır;
  modüle özgü eval seti implementasyon başladığında tanımlanır (henüz yok —
  `docs/16`'ya genel AI eval açık maddesi, `docs/16` AI-02 ile ilişkili).
- **Phase**: Mimari olarak Stage 0'dan itibaren pre-wired (port/event/izin
  tanımlı); etkinleştirme fazı için bkz. `docs/32` ilgili tablo satırı ve
  `docs/26`.
