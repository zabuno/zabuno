# OPT-11 — Scheduled Publishing

**PLANNING ONLY. Şu an çalıştırılamaz.**

**Amaç**: Bir menünün belirli bir tarih/saatte otomatik yayınlanmasını
sağlamak.
**Bounded context**: Publication modülünün üzerine kurulur, CORE-10 Workflow
motoruyla zaman tetiklemesi.
**Owner**: Engineering. **Sınıf**: Optional (M1).
**Bağımlılıklar**: Publication, CORE-10.
**Public contracts/events**: `PublicationScheduled`, `ScheduledPublicationFired`.
**Tenant isolation**: Aynı.
**Permissions**: `menu.publish.schedule`.
**Entitlement**: Plan'a bağlı.
**ECA hooks**: Zaman-bazlı tetikleyici zaten ECA'nın bir örneğidir.
**AI-off/on**: AI'dan bağımsız.
**UX**: Publish ekranında "şimdi" veya "zamanla" seçeneği.
**States**: `scheduled → fired → published/failed`.
**Retention**: Zamanlanmış ama iptal edilen publication'lar loglanır.
**Observability**: Zamanlama doğruluğu (gecikme).
**Security**: Yok.
**A11y/i18n**: Standart.
**Phase delivery**: Stage 2 Post-MVP.
**Acceptance**: Zamanlanan publish'in doğru saatte tetiklendiğinin testi;
cron gecikmesi durumunda uyarının tetiklendiğinin testi (`docs/16` OBS-01 ile
ilişkili).
**Rollback**: Zamanlanmış publish iptal edilebilir (tetiklenmeden önce).
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: En uygun yayın zamanı önerisi (trafik desenine göre)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Yayın zamanlama meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Zamanlanmış yayının gerçekleşmesi deterministik cron'dur
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
