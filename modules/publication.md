# Publication

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

## Amaç
Restoran panelindeki düzenleme verisiyle müşteriye gösterilen yayınlanmış
veriyi ayırmak.

## Bounded context
Draft → immutable snapshot → current pointer akışı. Menu Catalog'un editable
verisini **tüketir**, kendi immutable kopyasını üretir.

## Owner
Engineering.

## Sınıf
Required product.

## Bağımlılıklar
Menu Catalog, CORE-13 (görsel snapshot'a dahil edilir).

## Public contracts / events
`PublicationRequested`, `PublicationSucceeded`, `PublicationFailed`,
`PublicationSuperseded` event'leri; `PublicationPort::current(menu)`.

## Tenant isolation
Publication tenant/location-scoped.

## Permissions
`menu.publish`.

## Entitlement / quota
Publication sıklığı sınırlanmaz (MVP'de); scheduled publish (OPT-11) plan'a
bağlı olabilir.

## ECA hooks
`PublicationFailed` → uyarı bildirimi.

## AI-off / AI-on davranışı
AI'dan bağımsız — publish işlemi her zaman insan tetiklemesiyle olur (`docs/06`
§7 destructive/publish action kuralı).

## UX one-click journey
Publish checklist → tek tık yayınlama → anlık public menu güncellemesi.

## States
`pending → generating → published → failed → superseded` (`docs/10` §2).

## Data retention / export
Eski publication versiyonları saklanır (rollback/version comparison için, M1).

## Observability
Publish süresi, başarısızlık oranı, cache invalidation gecikmesi.

## Security / privacy
Yok (özel risk yok).

## Accessibility / i18n
Publish ekranı WCAG 2.2 AA.

## Phase delivery
Stage 1 MVP — draft/preview/publish/snapshot; Stage 2'de scheduled
publish/rollback/version comparison/approval workflow (OPT-11, OPT-12).

## Acceptance
Yarım kalmış değişikliğin müşteriye asla görünmediğinin testi (atomicity);
publish failure durumunda son başarılı sürümün korunduğunun testi.

## Rollback
Disable edilemez (required product, MVP kritik yolu). Publication'ın kendi
"rollback" özelliği (eski sürüme dönme) M1'dedir.

## Open questions
Küçük publication senkron mu ağır görsel işleme queue'ya mı — karar `docs/04`
§2 notunda: küçük snapshot senkron, ağır görsel işleme queue'ya bırakılır.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Yayın-öncesi kalite kontrol açıklaması (broken link, eksik görsel, WCAG uyarısı — yayınlama kararını değil, uyarıyı üretir)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Snapshot içerik meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Publish/rollback kararı her zaman deterministik ve insan tetiklidir
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
