# Iyzico Payment

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Iyzico ödeme sağlayıcısıyla checkout/3DS/subscription/webhook entegrasyonunu
sağlamak.

## Bounded context
Ödeme sağlayıcı adaptörü. Abonelik durum mantığı Pricing/Subscription/Billing
modülünde.

## Owner
Finance Operator + Engineering + Security.

## Sınıf
Required product.

## Bağımlılıklar
Pricing/Subscription/Billing, CORE-12, CORE-06 (secret'lar).

## Public contracts / events
`PaymentSucceeded`, `PaymentFailed`, `RefundIssued`, `WebhookReceived`
event'leri.

## Tenant isolation
Ödeme kayıtları tenant-scoped.

## Permissions
`billing.manage` (Iyzico ayrı bir izin üretmez, Billing modülünün izinlerini
kullanır).

## Entitlement / quota
Yok.

## ECA hooks
`PaymentFailed` → retry + bildirim tetiklenir.

## AI-off / AI-on davranışı
AI'dan tamamen bağımsız — ödeme akışına AI müdahalesi **yasaktır**.

## UX one-click journey
Checkout formu tek adımda (3DS gerektiğinde ek doğrulama adımı).

## States
`initiated → processing → succeeded/failed → refunded/chargeback`.

## Data retention / export
Kart bilgisi **saklanmaz**; işlem kaydı (tutar, tarih, durum) saklanır.

## Observability
Ödeme başarı oranı, webhook gecikme süresi, reconciliation uyuşmazlığı.

## Security / privacy
Webhook V3 HMAC/signature doğrulama, replay protection, server-side tutar
doğrulama, idempotency/conversation ID (`docs/09` §5).

## Accessibility / i18n
Checkout formu WCAG 2.2 AA.

## Phase delivery
Stage 1 MVP — **çalışan** sandbox dikey dilimi (adaptör, sandbox
checkout/3DS, server-side tutar doğrulama, idempotency/conversation ID,
imzalı webhook doğrulama + replay protection, deterministik
success/failure durumları; canlı/production para akışı yok). Recurring
payment, invoice, refund, chargeback, reconciliation gibi daha derin akışlar
M1/Post-MVP'de eklenir. Stage 3 GTM'de **live switch**: yalnız operasyonel/
hukuki/güvenlik/reconciliation/rollback kapıları geçildikten sonra
(`docs/09` §6, `docs/26` §1).

## Acceptance
Webhook replay saldırısının engellendiğinin testi; server-side tutar
doğrulamasının client tutarını görmezden geldiğinin testi.

## Rollback
Live entegrasyonda güvenlik açığı bulunursa anında sandbox'a geri alınır
(`docs/20` rollback trigger).

## Open questions
Webhook SLA gecikmesi netleşmedi (`docs/16` PAY-01).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Ödeme anomali tespiti desteği (örn. "bu işlem coğrafi olarak alışılmadık" bilgilendirmesi, karar değil)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: İşlem meta verisi (kart bilgisi hiçbir koşulda AI'ya gönderilmez)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Ödeme onay/red/refund kararı AI'ya kesinlikle kapalıdır — yalnız insan/deterministik kural
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
