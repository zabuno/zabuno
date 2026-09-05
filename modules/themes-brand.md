# Themes / Brand

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
Marka görünümü (logo, renk, tipografi) ve 5 tema domeninin token/layout
yönetimini sağlamak.

## Brand asset ve renk sözleşmesi

- **Asset slotları** (sahiplik burada; upload/derivative pipeline `docs/07`
  §6'da genişletilir): logo, cover, profile/avatar (external/social paylaşım
  için), favicon, app icon, Open Graph (OG) share image.
  **Kodda bugün yalnız `users.avatar_media_asset_id` vardır**; logo yuvası
  tanımlıdır ama işlemcisi yoktur ve diğer dört yuvanın sütunu da yoktur
  (`docs/75` §Sınır, `docs/113` §5.4). Bu liste modülün KAPSAMIDIR, durumu
  değil — durumu kod söyler.
- **Renk rolleri**: primary, secondary, accent, neutral — her rol bir design
  token'a bağlanır; tema domenleri arası paylaşılan token seti (`ADR-L09`,
  `docs/03`).
- **Tipografi**: heading/body/mono font aileleri, en az bir fallback stack.
- **Kalite kapısı**: safe contrast (WCAG 2.2 AA otomatik kontrol,
  aşağıdaki ## Accessibility / i18n) + draft → preview → publish → rollback
  döngüsü (## States) her renk/asset değişikliği için zorunludur — doğrudan
  canlıya yazma yoktur.
  **Kontrast kapısı FF-174 ile kuruldu ve kısıtı ÖLÇÜYE çevirdi**
  (`docs/113` §5.2): kiracı tek tek renk değeri girmez, bir TON verir;
  ürün `App\Domain\Branding\BrandSkin` ile yüzey/metin/kenar rampasını
  türetir, her metin/zemin çiftini açık ve koyu temada ayrı ölçer ve eşiği
  geçene kadar açıklığı ayarlar — ton ve doygunluk korunur. Hesaplanan rampa
  ve ölçülen oranlar YAYIN anlık görüntüsüne donar (`MenuIdentity::toSnapshot`),
  yani sonradan değişen bir marka rengi geçmiş bir yayını boyamaz. Kiracının
  seçebildiği ikinci eksen BİÇİMDİR ve orada değer değil SEÇENEK seçilir
  (`SkinVariant`, `resources/css/aep/tokens/variants.css` `data-variant`).
  Draft → preview → rollback döngüsünün tema tarafı hâlâ yoktur.

## Bounded context
Tema token yönetimi. Sayfa içeriği Content/Frontpages'in, sayfa iskeleti Page
Composition'ın sorumluluğunda.

## Owner
Design + Engineering.

## Sınıf
Required product.

## Bağımlılıklar
CORE-13 (logo/marka görseli).

## Public contracts / events
`ThemePort::tokens(domain, tenant)`; `ThemePublished` event'i.

## Tenant isolation
Marka tokenları tenant-scoped; platform tema (storefront/superadmin) platform
geneli.

## Permissions
`WorkspaceManage` (düzenleme) ve `MenuPublish` (yayına alma).

Bu satırda bir zamanlar `theme.manage` yazıyordu ve kodda karşılığı hiç
olmadı. Ayrı bir izin icat etmek aynı işi iki kapıdan geçirmek olurdu:
marka rengi bugün de `WorkspaceManage` ile düzenleniyor
(`UpdateBrandController.php`) ve `Permission` enum'una eklenen her değer beş
rolün beşinde de açıkça karara bağlanmayı gerektirir. Skin'in **yayına**
girmesi ise `MenuPublish`'e bağlıdır, çünkü skin misafirin gördüğü şeyi
değiştirir (`docs/113` §9).

## Entitlement / quota
Custom Branding (OPT-08) plana bağlı; anahtar `branding.custom` (FF-174).

## ECA hooks
Yok.

## AI-off / AI-on davranışı
AI'dan bağımsız (AI destekli tema önerisi ileri özellik, opsiyonel).

## UX one-click journey
Renk paleti seçiminde tek tık canlı önizleme.

## States
Tema: `draft → preview → published → rolled_back`.

## Data retention / export
Önceki tema versiyonları rollback için saklanır.

## Observability
Tema publish sıklığı.

## Security / privacy
Yok.

## Accessibility / i18n
Renk kontrastı WCAG 2.2 AA otomatik kontrolü (yayınlama öncesi kalite kapısı).
Metin çiftleri 4.5:1 (WCAG 1.4.3), metin olmayan çizgi/kenar 3:1 (1.4.11) —
eşikler rolün kendisinde sahiplenilir (`BrandRampRole::floor()`).

## Phase delivery
Stage 1 MVP — temel marka görünümü; Stage 5'te Custom Branding (OPT-08) tam
kapsam.

## Acceptance
Kontrast kalite kapısının düşük kontrastlı bir paleti reddettiğinin testi.

## Rollback
Disable edilemez (required product) ama tema rollback'i kendi başına bir
özelliktir (draft/preview/publish/rollback döngüsü).

## Open questions
Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Marka renk paleti/tema token önerisi (logo/marka rengine göre)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Marka varlıkları (logo/renk — genel işletme verisi)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Tema uygulama/yayına alma kararı insan onayı gerektirir
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
