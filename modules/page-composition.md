# Page Composition

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
Header/footer/navigasyon/component slot **yapısını** yönetmek — "hangi bileşen
nerede" (`docs/12` §6).

## Bounded context
Yapı/iskelet. İçerik Content/Frontpages'in sorumluluğunda.

## Owner
Design + Engineering.

## Sınıf
Required product.

## Bağımlılıklar
Themes/Brand.

## Public contracts / events
`PageCompositionPort::layout(page)`; `LayoutPublished` event'i.

## Tenant isolation
Restoran paneli navigasyonu tenant-scoped değildir (sabit ürün navigasyonu);
storefront/marketing sayfa kompozisyonu platform geneli.

## Permissions
`page.compose.manage`.

## Entitlement / quota
Yok.

## ECA hooks
Yok.

## AI-off / AI-on davranışı
AI'dan bağımsız.

## UX one-click journey
Sürükle-bırak slot düzenleme (M1 hedefi; MVP'de code-managed olabilir, `docs/02`
§11.8 kaynak dokümandan korunmuş not).

## States
Layout: `draft → preview → published`.

## Data retention / export
Yok.

## Observability
Yok (görece düşük riskli modül).

## Security / privacy
Yok.

## Accessibility / i18n
Slot yapısı WCAG 2.2 AA landmark/heading hiyerarşisine uygun olmalı.

## Phase delivery
Stage 1 MVP — code-managed temel yapı; Stage 2+'de sürükle-bırak editör.

## Acceptance
Global panel shell bileşenlerinin (`docs/06` §4) her admin domeninde doğru
render edildiğinin testi.

## Rollback
Disable edilemez (required product, panel shell'in temeli).

## Open questions
Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Sayfa slot/layout kompozisyon önerisi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Sayfa içerik meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Yayına alma Publication modülünün deterministik kararıdır
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
