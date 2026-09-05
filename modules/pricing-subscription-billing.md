# Pricing / Subscription / Billing

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
Plan tanımı, tenant plan ataması, abonelik yaşam döngüsü ve fatura kayıtlarını
yönetmek.

## Bounded context
Plan/abonelik durumu. Ödeme sağlayıcı entegrasyonu ayrı modülde (Iyzico
Payment); para hesaplama CORE-12'de.

## Owner
Finance Operator + Engineering.

## Sınıf
Required product.

## Bağımlılıklar
CORE-04, CORE-12, Iyzico Payment (M1+).

## Public contracts / events
`SubscriptionActivated`, `SubscriptionPastDue`, `SubscriptionSuspended`,
`SubscriptionCanceled`, `PlanChanged` event'leri.

## Tenant isolation
Abonelik tenant-scoped (bir workspace bir aktif abonelik).

## Permissions
`billing.view`, `billing.manage` (`docs/02` §5).

## Entitlement / quota
Bu modülün **kendisi** CORE-04'ün veri kaynağıdır (hangi plan → hangi limitler).

## ECA hooks
`SubscriptionPastDue` → uyarı bildirimi; `SubscriptionSuspended` → QR
politikası tetiklenir (`docs/09` §7).

## AI-off / AI-on davranışı
AI'dan bağımsız — abonelik durumu değişimi asla AI kararıyla tetiklenmez.

## UX one-click journey
Plan yükseltme tek tık (M1'de online ödeme ile); MVP'de manuel süperadmin
ataması.

## States
`trialing → active → past_due → grace → suspended → canceled → expired`
(`docs/10` §2).

## Data retention / export
Fatura kayıtları uzun süre saklanır (mali mevzuat).

## Observability
MRR, churn, trial-conversion (M1 metrikleri, `docs/26` §2).

## Security / privacy
Kart bilgisi bu modülde **saklanmaz** (Iyzico Payment modülüne devredilir).

## Accessibility / i18n
Plan/Kullanım ekranı WCAG 2.2 AA.

## Phase delivery
Stage 1 MVP — manuel ödeme kaydı **ve** çalışan Iyzico sandbox dikey dilimi
birlikte (biri diğerinin yedeği değildir, `docs/09` §6); Stage 3 GTM'de
live switch (yalnız operasyonel/hukuki/güvenlik/reconciliation/rollback
kapıları geçildikten sonra).

## Acceptance
Abonelik sonrası QR politikasının (`docs/09` §7) her durumda (active/past_due/
grace/suspended/canceled) doğru davrandığının testi.

## Rollback
Disable edilemez (required product, MVP kritik yolu).

## Open questions
Trial/grace period gün sayıları netleşmedi (`docs/16` BIZ-03/04).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Churn riski / maliyet-fayda içgörüsü (asla otomatik fiyatlandırma veya ücretlendirme değil)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Kullanım/abonelik meta verisi (kart bilgisi asla değil)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Fatura/ücretlendirme kararı ve finalitesi CORE-12'ye aittir, AI'ya kapalı
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
