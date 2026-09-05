# SEO / Search & Discovery

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
Tek birleşik SEO capability map'i (`docs/12` §7 — governed alias/facet/
channel/risk-compliance mapping) uygulamak: structured data, canonical,
hreflang, robots/sitemap, Core Web Vitals ve ilgili tüm facet'ler.

## Bounded context
Teknik + içerik SEO altyapısı. `docs/12` §7'de sayılan tüm owner etiketleri
(AEO/GEO/LLMO/AIO gibi AI-surface alias'ları dahil) bu tek modülün
kapsamındadır, ayrı ayrı motorlar/modüller **değildir**. Gray/black/negative
SEO prohibited/defensive, Parasite SEO high-risk governance, Barnacle SEO
governed external discovery bağımlılığı olarak ele alınır — hiçbiri
otomatik uygulanan bir "özellik" değildir (`docs/12` §7, §8).

## Owner
Growth/Marketing Operator (`docs/02` §1.1) + Engineering.

## Sınıf
Required product.

## Bağımlılıklar
Content/Frontpages, Publication (public menu SEO meta'sı için).

## Public contracts / events
`SitemapRegenerated`, `StructuredDataPublished` event'leri.

## Tenant isolation
Public menu SEO meta'sı tenant-scoped; platform SEO ayarları platform geneli.

## Permissions
`seo.manage`.

## Entitlement / quota
Yok (SEO temel bir haktır, kısıtlanmaz).

## ECA hooks
Yok.

## AI-off / AI-on davranışı
AI SEO içerik önerebilir; `llms.txt`'in sıralama girdisi olduğu iddiası
**reddedilir** (`docs/12` §8).

## UX one-click journey
SEO meta önizleme (Google/sosyal medya kart önizlemesi) tek ekranda.

## States
Yok.

## Data retention / export
Sitemap/redirect geçmişi saklanır.

## Observability
Indexation oranı, Core Web Vitals skoru.

## Security / privacy
pSEO thin-content/spam kalite kapısı zorunlu (`docs/12` §7).

## Accessibility / i18n
hreflang + RTL uyumlu meta üretim.

## Phase delivery
Stage 3 GTM — temel technical+local facet'ler; Growth Stage'de pSEO ölçek
(kalite kapısıyla).

## Acceptance
Structured data'nın geçerli JSON-LD şemasına uyduğunun testi; thin-content
kapısının düşük kaliteli sayfayı reddettiğinin testi.

## Rollback
Disable edilirse public sayfalar SEO meta'sız (ama işlevsel) kalır.

## Open questions
pSEO thin-content eşiği netleşmedi (`docs/16` SEO-01).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Meta başlık/açıklama/JSON-LD taslak önerisi (SEO capability map'e uygun)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Sayfa içerik meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Yayına alma insan onayı gerektirir; thin-content kalite kapısı deterministik kural + insan review'dur
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
