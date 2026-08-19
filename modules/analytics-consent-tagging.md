# Analytics / Consent / Tagging

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
First-party event ledger'ı tutmak ve consent-gated üçüncü taraf tag'leri
yönetmek.

## Bounded context
Event toplama + consent kontrolü. Raporlama görünümleri (dashboard) ayrı bir
sunum katmanıdır, bu modül veri kaynağıdır.

## Owner
Engineering + Growth/Marketing Operator (`docs/02` §1.1).

## Sınıf
Required product.

## Bağımlılıklar
CORE-16 (consent kaydı), QR Destination (QR Resolve event'i için). GA4/Yandex
Metrica inbound reporting adaptörü (Growth Stage, `docs/12` §5a) için
Integration Hub'ın secret-custody/tenant-scoped bağlantı altyapısı kullanılır
(`modules/integration-hub.md`) — bu modül provider verisini yalnız **tüketir**,
bağlantı kaydının kendisi Integration Hub'da yaşar.

## Public contracts / events
`ScanRecorded` (QR Resolve), `MenuOpened` (Confirmed Menu Open) — ayrımı
`docs/12` §2.

## Tenant isolation
Event'ler tenant-scoped.

## Permissions
`analytics.view`.

## Entitlement / quota
Retention süresi ve Advanced Analytics (OPT-06) plana bağlı.

## ECA hooks
Anormal scan sıçraması ECA kuralına bağlanabilir (uyarı, Post-MVP).

## AI-off / AI-on davranışı
AI'dan bağımsız (AI destekli anomali tespiti ileri özellik).

## UX one-click journey
Dashboard'da tek bakışta bugün/7 gün/30 gün scan özeti.

## States
Yok (event'ler immutable append-only).

## Data retention / export
Raw event retention sınırlı; aggregate veri daha uzun saklanabilir.

## Observability
Bu modülün kendisi observability'nin analytics tarafıdır.

## Security / privacy
IP anonimleştirme, PII yok, bot filtreleme (`docs/12` §4).

## Accessibility / i18n
Dashboard grafikleri WCAG 2.2 AA (renk-kör dostu paletler, veri tablosu
alternatifi).

## Phase delivery
Stage 1 MVP — temel scan sayaçları; PMF Stage'de cohort/retention asıl işlevi
kazanır (`docs/21`); Growth Stage'de GA4/Yandex Metrica inbound reporting
adaptörü eklenir (`docs/12` §5a, `docs/22`).

## Acceptance
QR Resolve ile Confirmed Menu Open'ın ayrı sayıldığının testi; consent
geri çekilince tag'lerin durduğunun testi.

## Rollback
Disable edilemez (required product — MVP temel analitik gerektirir).

## Open questions
Unique scan tanımı (pencere/fingerprint/consent) netleşmedi (`docs/16` ANL-02).
GA4/Yandex Metrica inbound reporting adaptörünün kapsam/quota/attribution
belirsizliği (`docs/16` ANL-03, `docs/12` §5a).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Trafik/dönüşüm içgörü özetleme (first-party event ledger üzerinden)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Agregat/anonimleştirilmiş event verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Consent kararının kendisi kullanıcıya aittir, AI consent vermez/almaz
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
