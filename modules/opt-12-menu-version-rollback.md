# OPT-12 — Menu Version Rollback

**PLANNING ONLY. Şu an çalıştırılamaz.**

**Amaç**: Önceki bir publication versiyonuna geri dönmeyi sağlamak.
**Bounded context**: Publication modülünün immutable snapshot geçmişinin
üzerine kurulur (snapshot'lar zaten saklanıyor, bu modül onları **kullanılabilir
hale getirir**).
**Owner**: Engineering. **Sınıf**: Optional (M1).
**Bağımlılıklar**: Publication.
**Public contracts/events**: `PublicationRolledBack`.
**Tenant isolation**: Aynı.
**Permissions**: `menu.publish.rollback`.
**Entitlement**: Plan'a bağlı.
**ECA hooks**: Yok.
**AI-off/on**: AI'dan bağımsız — rollback her zaman insan onaylı (destructive
olmayan ama yüksek etkili bir işlem, `docs/06` §7 ruhuna uygun ekstra onay).
**UX**: Versiyon geçmişinde tek tık "bu versiyona dön" + onay dialogu.
**States**: Rollback işlemi bir yeni `PublicationSuperseded` transition'ıdır
(eski versiyon "current" işaretlenir, veri kaybı olmaz).
**Retention**: Tüm versiyonlar saklandığı için rollback veri kaybettirmez.
**Observability**: Rollback sıklığı (sık rollback, publish sürecinde bir sorun
sinyali olabilir).
**Security**: Yok.
**A11y/i18n**: Standart.
**Phase delivery**: Stage 2 Post-MVP.
**Acceptance**: Rollback sonrası public menünün gerçekten eski versiyonu
gösterdiğinin testi.
**Rollback (kendi rollback'i)**: N/A — bu modülün kendisi zaten bir rollback
mekanizmasıdır.
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: İki versiyon arası fark açıklaması (ne değişti, olası etki)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Snapshot diff meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Rollback tetikleme kararı insan eylemidir
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
