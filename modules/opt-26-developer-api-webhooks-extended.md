# OPT-26 — Developer API / Webhooks (Genişletilmiş)

> **DURUM BURADA YAZMAZ — KOD SÖYLER.**
>
> Bu satırda bir zamanlar "PLANNING ONLY. Şu an çalıştırılamaz." yazıyordu
> ve **altmış iki modül dosyasının altmış ikisinde de aynı cümle vardı** —
> menü kataloğu, yayınlama, karekod ve medya dahil. Oysa 2026-09-05
> envanterinde on sekiz modül uygulanmış, on dokuzu kısmen uygulanmış
> çıktı. Yani cümle en az on sekiz dosyada açıkça yanlıştı.
>
> Sebebi bir ihmal değil, YAPININ KENDİSİYDİ: bir modül teslim edildiğinde
> kimse tanım dosyasına geri dönmüyor. Aynı cümleyi altmış iki dosyada
> güncel tutmak, aynı hatayı daha büyük ölçekte tekrarlamak olurdu.
>
> Bu yüzden durum alanı **kaldırıldı**. Bu dosya modülün NE OLDUĞUNU
> anlatır; ÇALIŞIP ÇALIŞMADIĞINI kod söyler ve türetilmiş envanter gösterir
> (`docs/111`). Bir soru "bu modül var mı?" ise cevabı burada aramayın.

**Amaç**: Tenant'ın kendi entegrasyonlarını yazabileceği genel amaçlı, belgeli
bir public API + webhook seti sunmak.
**Bounded context**: Integration Hub'ın **tenant-yüzü genişlemesidir** —
Integration Hub dahili/entegrasyon-özel altyapıyken, bu modül dış geliştiricilere
açık bir yüzey sağlar.
**Owner**: Engineering. **Sınıf**: Optional (Enterprise edition ağırlıklı).
**Bağımlılıklar**: Integration Hub, CORE-03 (API scope-based yetki).
**Public contracts/events**: `APIRequestReceived`, `WebhookSubscribed`.
**Tenant isolation**: API key başına scope-limited erişim.
**Permissions**: `developer.api.manage`.
**Entitlement**: Enterprise edition (bkz. `docs/26` §2).
**ECA hooks**: Webhook aboneliği ECA'nın dış-sistem action'ı olarak kullanılır.
**AI-off/on**: AI'dan bağımsız.
**UX**: API dokümantasyonu (OpenAPI) + self-service key üretimi.
**States**: API key: `active → revoked`.
**Retention**: API kullanım logları saklanır (rate-limit ve fatura için).
**Observability**: Endpoint bazlı kullanım hacmi, hata oranı.
**Security**: Rate limiting, OWASP API güvenlik kontrol listesi.
**A11y/i18n**: Dokümantasyon portalı WCAG 2.2 AA.
**Phase delivery**: Stage 6 Enterprise (`docs/23`).
**Acceptance**: Scope dışı bir API çağrısının reddedildiğinin testi.
**Rollback**: Disable edilirse tüm API key'ler devre dışı kalır, dahili işlevler
etkilenmez.
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Webhook payload/şema taslağı önerisi (geliştirici için)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: API şema meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Üretim endpoint'inin etkinleştirilmesi insan onayı gerektirir
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
