# CORE-07 — Audit & Event Outbox

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Kim/ne zaman/hangi veriyi değiştirdi kaydını tutmak ve cross-module event
dağıtımını (outbox pattern) sağlamak.

## Bounded context
Audit kaydı + event dağıtım altyapısı. İş mantığı içermez, yalnız kayıt/dağıtım
yapar.

## Owner
Architecture + Security.

## Sınıf
Core.

## Bağımlılıklar
CORE-01, CORE-02 (actor/tenant bilgisi için).

## Public contracts / events
`AuditLogger::record(actor, tenant, action, resource_type, resource_id, before,
after, request_id, ip, user_agent)`; `EventOutbox::publish(event)` — cross-module
iletişimin tek resmi yolu (`docs/03` ADR-L05).

## Tenant isolation
Audit kayıtları tenant-scoped; platform-seviyeli audit ayrı görünüm.

## Permissions
`audit.view.platform`, `audit.view.tenant` (salt-okunur, Auditor rolü `docs/02`).

## Entitlement / quota
Audit retention süresi plana göre değişebilir (`docs/09` §4).

## ECA hooks
Bu modülün kendisi ECA'nın event kaynağıdır — her audit kaydı potansiyel bir
ECA event'i olabilir.

## AI-off / AI-on davranışı
AI eylemleri de audit'e yazılır (`docs/14` §3).

## UX one-click journey
Audit log ekranında filtre + arama; tek tık export.

## States
Yok (audit kayıtları immutable, state geçişi yok).

## Data retention / export
Audit kaydı **değiştirilemez** (immutable); retention süresi CORE-15 politikası
ile yönetilir.

## Observability
Bu modülün kendisi observability'nin bir parçasıdır.

## Security / privacy
Audit kaydı değiştirilebilir mi sorusu `docs/16` güvenlik bilinmeyenlerinde —
cevap: **hayır**, append-only olmalı.

## Accessibility / i18n
Audit ekranı WCAG 2.2 AA.

## Phase delivery
Stage 1 MVP — kritik işlemler listesi (`docs/01` §6) tam kapsam.

## Acceptance
Audit kaydının immutable olduğunun testi (update/delete denemesi başarısız
olmalı); event outbox'ın at-least-once teslimat garantisi testi.

## Rollback
Core modül — devre dışı bırakılamaz.

## Open questions
Şifreli backup var mı (`docs/16` güvenlik bilinmeyenleri).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Audit log anomali özetlemesi (örn. "bu gün olağandışı sayıda fiyat değişikliği" içgörüsü)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Audit meta verisi (actor/action/timestamp, önceden redaction'lı)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Audit kaydının kendisi immutable ve deterministiktir; AI kayda yazmaz/değiştirmez
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
