# OPT-19 — Marketing Automation

**PLANNING ONLY. Şu an çalıştırılamaz.**

**Amaç**: Otomatik e-posta/SMS pazarlama akışları (drip campaign) sağlamak.
**Bounded context**: CORE-11 ECA motorunun pazarlama-özel bir uygulamasıdır;
CORE-14 Notifications'ı kullanır.
**Owner**: Product + (Growth/Marketing rolü). **Sınıf**: Optional (M2).
**Bağımlılıklar**: CORE-11, CORE-14, Mini CRM/OPT-18, CORE-16 (consent —
pazarlama izni olmadan gönderim yasak).
**Public contracts/events**: `AutomationSequenceStarted`, `AutomationStepSent`.
**Tenant isolation**: Aynı.
**Permissions**: `marketing.automation.manage`.
**Entitlement**: M2 edition.
**ECA hooks**: Bu modülün kendisi ECA'nın bir uygulama alanıdır.
**AI-off/on**: AI içerik önerebilir; gönderim insan onaylı şablonla.
**UX**: Sequence builder (adım adım e-posta/SMS akışı tasarımı).
**States**: Sequence: `draft → active → paused → completed`.
**Retention**: Gönderim geçmişi saklanır.
**Observability**: Açılma/tıklama oranı.
**Security**: Pazarlama izni olmayan contact'a gönderim **backend'de
engellenir** (yalnız UI kontrolü değil).
**A11y/i18n**: Şablonlar çok dilli, erişilebilir e-posta HTML.
**Phase delivery**: Growth Stage.
**Acceptance**: Consent'i olmayan bir contact'a gönderim denemesinin
reddedildiğinin testi.
**Rollback**: Disable edilirse aktif sequence'lar duraklatılır, veri kaybolmaz.
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Kampanya otomasyon adımı için içerik taslağı önerisi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Pazarlama içeriği
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Otomasyonun canlıya alınması insan onayı + ECA kural gerektirir
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
