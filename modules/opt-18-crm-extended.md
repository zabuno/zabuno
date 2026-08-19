# OPT-18 — CRM (Genişletilmiş)

**PLANNING ONLY. Şu an çalıştırılamaz.**

**Amaç**: Mini CRM'in ötesinde gelişmiş segment/otomasyon/pipeline yönetimi
sağlamak.
**Bounded context**: Mini CRM'in **entitlement seviyesi genişlemesidir** — ayrı
bir veri modeli değil, aynı Contact/Segment üzerine gelişmiş yetenek.
**Owner**: Product + Engineering. **Sınıf**: Optional (M2).
**Bağımlılıklar**: Mini CRM, CORE-11 (gelişmiş ECA senaryoları).
**Public contracts/events**: `PipelineStageChanged`.
**Tenant isolation**: Aynı.
**Permissions**: Mini CRM izinleri + `crm.pipeline.manage`.
**Entitlement**: Growth+ edition.
**ECA hooks**: Pipeline aşama geçişleri ECA ile otomatikleştirilebilir.
**AI-off/on**: AI lead skorlama önerebilir; karar insanda.
**UX**: Kanban-tarzı pipeline görünümü.
**States**: Pipeline stage'leri tenant tarafından özelleştirilebilir.
**Retention**: Mini CRM ile aynı.
**Observability**: Pipeline conversion oranı.
**Security**: Yok (Mini CRM güvenlik modeliyle aynı).
**A11y/i18n**: Standart.
**Phase delivery**: Growth Stage.
**Acceptance**: Mini CRM'den bu modüle geçişte veri kaybı olmadığının testi.
**Rollback**: Disable edilirse Mini CRM temel görünümüne döner.
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Genişletilmiş segment özetleme ve sıradaki-en-iyi-eylem önerisi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Contact/segment meta verisi (PII, redaction'a tabi)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: İletişim/consent kararı AI'ya devredilmez
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
