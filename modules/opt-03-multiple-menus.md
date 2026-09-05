# OPT-03 — Multiple Menus

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

**Amaç**: Bir location'ın birden fazla menüsünü (kahvaltı, akşam, bar) ayrı ayrı
yönetmesini sağlamak.
**Bounded context**: Menu Catalog'un veri modeli zaten Menu 1:N destekler
(`docs/05` §5); bu modül yalnız **UI/UX** ve zamanlama katmanını ekler (hangi
menü ne zaman aktif).
**Owner**: Product + Engineering. **Sınıf**: Optional (M1).
**Bağımlılıklar**: Menu Catalog, Publication.
**Public contracts/events**: `MenuScheduleActivated`.
**Tenant isolation**: Menu Catalog ile aynı.
**Permissions**: Menu Catalog izinleri yeterli.
**Entitlement**: Plan'a bağlı menü sayısı limiti.
**ECA hooks**: Saat/gün bazlı otomatik menü değişimi (örn. kahvaltı menüsü
11:00'de bar menüsüne döner).
**AI-off/on**: AI'dan bağımsız.
**UX**: Menü listesinde tek tık aktif/pasif değişimi.
**States**: Her menü kendi Publication state'ine sahip.
**Retention**: Menu Catalog ile aynı.
**Observability**: Menü bazlı scan/publish istatistiği.
**Security**: Yok. **A11y/i18n**: Standart.
**Phase delivery**: Stage 2 Post-MVP.
**Acceptance**: Aynı anda yalnız bir menünün "aktif" (public'e görünen)
olduğunun testi (çakışma yok).
**Rollback**: Disable edilirse tek ana menüye geri döner (MVP davranışı).
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Gün içi zaman dilimine göre menü segmentasyonu önerisi (kahvaltı/öğle/akşam)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Menü meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Menü oluşturma/atama insan eylemidir
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
