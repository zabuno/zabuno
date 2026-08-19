# OPT-10 — Multi-branch Management

**PLANNING ONLY. Şu an çalıştırılamaz.**

**Amaç**: Bir Brand altında birden fazla Location'ın tek panelden yönetilmesini
sağlamak.
**Bounded context**: `docs/05` §1'deki 1:N ilişkinin **UI/UX ve operasyonel**
katmanı (veri modeli zaten hazırdı).
**Owner**: Product + Engineering. **Sınıf**: Optional (Growth edition).
**Bağımlılıklar**: CORE-02, Menu Catalog, QR Destination.
**Public contracts/events**: `LocationSwitched`, `CrossBranchReportGenerated`.
**Tenant isolation**: Şubeler arası veri izolasyonu **aynı workspace içinde
bile** korunur (bir şubenin verisi diğerine sızmaz, `docs/22` acceptance
evidence).
**Permissions**: `location.switch`, `location.manage.all` (zincir-geneli
görünürlük, sınırlı role).
**Entitlement**: Growth+ edition, şube sayısı limiti.
**ECA hooks**: Şube-bazlı kural override'ları.
**AI-off/on**: AI'dan bağımsız.
**UX**: Location selector ile tek tık şube değişimi (`docs/06` §4).
**States**: Her location kendi Menu/QR state'lerine sahip, bağımsız.
**Retention**: Şube kapatılırsa veri arşivlenir.
**Observability**: Şube başına aktif kullanım karşılaştırması.
**Security**: Şube-arası tenant escape testi zorunlu.
**A11y/i18n**: Standart.
**Phase delivery**: Stage 5 Growth (`docs/22`).
**Acceptance**: Bir şubedeki fiyat değişikliğinin diğerini etkilemediğinin
testi.
**Rollback**: Disable edilirse tek-şube görünümüne döner, veri kaybolmaz.
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Şubeler arası performans karşılaştırma içgörüsü
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Agregat şube verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Şube oluşturma/silme insan eylemidir
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
