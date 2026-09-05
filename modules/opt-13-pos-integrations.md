# OPT-13 — POS Integrations

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

**Amaç**: Restoranın POS (Point of Sale) sistemiyle menü/sipariş senkronizasyonu
sağlamak.
**Bounded context**: Integration Hub üzerinden dış sisteme adapter.
**Owner**: Engineering. **Sınıf**: Optional (M2).
**Bağımlılıklar**: Integration Hub, Menu Catalog, Online Ordering (OPT-14, ileri
etkileşim).
**Public contracts/events**: `POSSyncCompleted`, `POSSyncFailed`.
**Tenant isolation**: Aynı.
**Permissions**: `pos.integration.manage`.
**Entitlement**: M2 edition.
**ECA hooks**: Senkronizasyon hatası → bildirim.
**AI-off/on**: AI'dan bağımsız.
**UX**: Entegrasyon kurulum sihirbazı (POS sağlayıcı seçimi + API key girişi).
**States**: `disconnected → connecting → synced → error`.
**Retention**: Sync logları saklanır.
**Observability**: Senkronizasyon başarı oranı, gecikme.
**Security**: POS API key'leri CORE-06'da güvenli saklanır.
**A11y/i18n**: Standart.
**Phase delivery**: Growth Stage (`docs/22`).
**Acceptance**: Senkronizasyon hatasının kullanıcıya doğru raporlandığının
testi.
**Rollback**: Bağlantı kesilirse menü manuel yönetime geri döner (veri kaybı
yok).
**Open questions**: Hangi POS sağlayıcıları önceliklendirilecek — henüz karar
yok, pazar araştırması gerektirir.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: agentic_guarded
- **Optional AI use case(ler)**: POS alan eşleme önerisi ve sandbox test senkronizasyonu
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Entegrasyon şema meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Üretim senkronizasyonunun etkinleştirilmesi insan onayı gerektirir
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
