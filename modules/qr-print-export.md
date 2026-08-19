# QR Print Export

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
QR'ın görsel tasarımını (designer) ve basılabilir/indirilebilir çıktısını
üretmek.

## Bounded context
Görsel üretim + PDF/PNG/SVG export. Destination çözümü QR Destination
modülünündür.

## Owner
Design + Engineering.

## Sınıf
Required product.

## Bağımlılıklar
QR Destination, CORE-13 (logo yükleme).

## Public contracts / events
`QRDesignPort::render(qr, theme, size)`; `QRExported` event'i.

## Tenant isolation
Tema/tasarım tercihleri tenant-scoped.

## Permissions
`qr.design.manage`.

## Entitlement / quota
Advanced QR Designer (OPT-05) plana bağlı; bulk export boyutu kısıtlanabilir.

## ECA hooks
Yok.

## AI-off / AI-on davranışı
AI'dan bağımsız.

## UX one-click journey
Bulk wizard ile tek akışta N adet QR üretimi ve tek PDF indirme (`docs/08` §5).

## States
Yok (tasarım bir varlık değil, render parametresi).

## Data retention / export
Üretilen PDF/PNG/SVG geçici olarak saklanabilir (tekrar indirme kolaylığı).

## Observability
Render süresi, server-side doğrulama başarısızlık oranı.

## Security / privacy
Yok (özel risk yok, logo upload CORE-13 güvenlik pipeline'ına tabi).

## Accessibility / i18n
Designer ekranı WCAG 2.2 AA; çıktı metinleri (CTA) çok dilli.

## Phase delivery
Stage 1 MVP — baseline: basic designer, PNG/SVG, server-side PDF export
(mPDF), masa/alan bulk wizard, altı temel tema, ISO 216 boyutları, scannability
doğrulama kapısı (`docs/08` §6 — sabit kapsam, açık bir M0/M1 seçimi değil).
Stage 2 Post-MVP — advanced tema designer, matbaa/vendor özellikleri
(bleed/crop mark, CMYK/EPS, büyük ölçekli production batch).

## Acceptance
Server-side scannability doğrulama testi (`docs/08` §4); ISO 216 boyut
doğruluğu testi.

## Rollback
Disable edilemez (required product, MVP kritik yolu — baseline PNG/SVG/PDF/
bulk export).

## Open questions
Yok — baseline PDF/bulk kapsamı `docs/08` §6 ile sabitlendi. Advanced tema
designer'ın Stage 2 içindeki kesin özellik kırılımı `docs/26` §1 OPT-05'e
bağlıdır.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Baskı boyutu/kontrast/layout önerisi (scannability skoruna göre)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Tasarım meta verisi (görsel içerik değil, boyut/renk parametresi)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Export/print tetikleme kararı insan eylemidir
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
