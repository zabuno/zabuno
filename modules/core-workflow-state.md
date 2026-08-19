# CORE-10 — Workflow / State Machine

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Modüllerin domain lifecycle'larını (durum makinelerini) çalıştıran ortak motor.

## Bounded context
Transition yürütme + loglama. Durum tanımları modül sahibinde kalır (`docs/10`
§2). Bu modül yalnız **domain** (sunucu tarafı) state machine'idir — frontend
(React) state kategorileri (local UI/URL/form/server-cache/offline-draft) bu
modülün kapsamı **dışındadır** ve bir Core modülü **değildir**; kanonik karar
`docs/10` §5'te yaşar, bu dosya onu tekrar tanımlamaz.

## Owner
Engineering.

## Sınıf
Core.

## Bağımlılıklar
CORE-07 (transition'lar audit'e yazılır).

## Public contracts / events
`WorkflowPort::transition(entity, from, to, guard?)`; `StateTransitioned`
event'i.

## Tenant isolation
Transition'lar tenant-scoped entity'ler üzerinde çalışır.

## Permissions
Modül-özel (örn. `menu.publish` — Menu Catalog modülünün tanımladığı izin,
motor kendisi izin üretmez).

## Entitlement / quota
Yok.

## ECA hooks
Her `StateTransitioned` event'i ECA motoruna (CORE-11) register edilebilir bir
tetikleyicidir.

## AI-off / AI-on davranışı
AI'dan bağımsız.

## UX one-click journey
Yok (arka plan motoru).

## States
Motorun kendisi state tanımlamaz — `docs/10` §2'deki örnekler modül
sahiplerinindir.

## Data retention / export
Transition geçmişi audit'te saklanır.

## Observability
Geçersiz transition denemesi sayısı (guard reddi).

## Security / privacy
Yetkisiz transition denemesi CORE-03 ile engellenir, bu motor yalnız *geçerli*
transition'ları yürütür.

## Accessibility / i18n
Durum etiketleri çevrilebilir.

## Phase delivery
Stage 1 MVP — Menu/QR/Subscription durum makineleri; Stage 2'de tam motor
(Symfony Workflow adapter, `docs/28`).

## Acceptance
Geçersiz transition'ın reddedildiğinin testi (örn. `published → draft` doğrudan
izin verilmiyorsa).

## Rollback
Core modül — devre dışı bırakılamaz.

## Open questions
Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Bir kaydın state geçmişine bakarak "sıradaki muhtemel adım" önerisi (yalnız bilgilendirme, geçişi tetiklemez)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: State geçiş meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: State geçişleri deterministik kural motoruyla yürütülür, AI geçiş tetiklemez
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
