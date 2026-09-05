# Content / Frontpages

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
Kurumsal site içeriğini (landing/pricing/features/FAQ) ve **pricing
projection**'ını yönetmek.

## Bounded context
İçerik verisi. Yapı Page Composition'ın, fiyat *kaynağı* Pricing/Subscription/
Billing'in sorumluluğunda — bu modül yalnız fiyatın **görünen projeksiyonunu**
yönetir (`docs/12` §6, `docs/09` §3).

## Owner
Product + Engineering.

## Sınıf
Required product.

## Bağımlılıklar
Page Composition, Pricing/Subscription/Billing (projection kaynağı olarak).

## Public contracts / events
`ContentPublished`, `PricingProjectionPublished` event'leri.

## Tenant isolation
Platform geneli (bu modül tenant içeriği değil, platformun kendi pazarlama
içeriğini yönetir).

## Permissions
`content.manage`, `pricing.projection.publish` (four-eyes — iki kişi onayı,
`docs/09` §3).

## Entitlement / quota
Yok.

## ECA hooks
Yok.

## AI-off / AI-on davranışı
AI, içerik taslağı **önerebilir**; publish insan onayı gerektirir.

## UX one-click journey
Draft → preview → four-eyes publish → rollback (`docs/09` §3).

## States
İçerik: `draft → preview → published → rolled_back`.

## Data retention / export
Önceki versiyonlar rollback için saklanır.

## Observability
Publish sıklığı, four-eyes onay bekleme süresi.

## Security / privacy
Pricing projection'ın gerçek plan/entitlement verisiyle **senkron** olduğunun
doğrulaması (yanlış fiyat gösterimi riski).

## Accessibility / i18n
İçerik WCAG 2.2 AA, çok dilli.

## Phase delivery
Stage 1 MVP — temel içerik; Stage 3 GTM'de tam pricing projection + four-eyes
onay akışı canlı.

## Acceptance
Pricing projection'ın gerçek plan verisinden **saparsa** yayının engellendiğinin
testi.

## Rollback
Disable edilemez (required product — kurumsal site MVP'nin parçası).

## Open questions
Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Landing/pricing/FAQ içerik taslağı önerisi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Pazarlama içeriği (PII değil)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Yayına alma kararı insan onayı gerektirir
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
