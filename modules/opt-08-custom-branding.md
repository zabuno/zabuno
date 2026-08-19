# OPT-08 — Custom Branding

**PLANNING ONLY. Şu an çalıştırılamaz.**

**Amaç**: Platform branding'inin (Zabuno logosu/ipucu) public menüden
kaldırılmasını/tam marka özelleştirmesini sağlamak.
**Bounded context**: Themes/Brand modülünün üzerine kurulur, bir entitlement
bayrağıdır.
**Owner**: Design + Finance Operator. **Sınıf**: Optional (M1/Growth edition).
**Bağımlılıklar**: Themes/Brand, CORE-04.
**Public contracts/events**: `BrandingLevelChanged`.
**Tenant isolation**: Aynı.
**Permissions**: Themes/Brand izinleri yeterli.
**Entitlement**: Bu modülün **kendisi** bir entitlement seviyesidir (`docs/09`
§4 "branding seviyesi").
**ECA hooks**: Yok.
**AI-off/on**: AI'dan bağımsız.
**UX**: Ayarlarda tek tık "platform branding'ini kaldır" (plan yeterliyse).
**States**: Yok.
**Retention**: Yok.
**Observability**: Yok.
**Security**: Yok. **A11y/i18n**: Standart.
**Phase delivery**: Stage 5 Growth.
**Acceptance**: Plan yetersizken bu ayarın backend'de de engellendiğinin testi
(yalnız UI gizleme değil).
**Rollback**: Plan düşerse branding otomatik geri döner (veri kaybı yok).
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Marka varlığı (logo/renk/font) tutarlılık önerisi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Marka varlıkları
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Yayına alma insan onayı gerektirir
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
