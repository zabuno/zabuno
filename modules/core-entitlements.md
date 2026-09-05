# CORE-04 — Entitlements & Usage Metering

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
Plan limitlerini ve modül erişimini backend seviyesinde zorunlu kılmak; yalnız
UI'da gizlemek değil.

## Bounded context
"Bu tenant bu özelliği/kotayı kullanabilir mi" sorusu. Plan/subscription
verisi Pricing/Subscription/Billing modülünden gelir, bu modül onu **uygular**.

## Owner
Finance Operator + Engineering.

## Sınıf
Core.

## Bağımlılıklar
CORE-02, Pricing/Subscription/Billing modülü.

## Public contracts / events
`EntitlementPort::check(tenant, feature|quota)`; `QuotaExceeded`,
`FeatureAccessDenied` event'leri.

## Tenant isolation
Her entitlement kontrolü tenant-scoped'dur.

## Permissions
Yok (CORE-03'ün tamamlayıcısıdır, kendi izin string'i üretmez).

## Entitlement / quota
Bu modülün **kendisi** entitlement/quota altyapısıdır — envanter `docs/09` §4'te.

## ECA hooks
`QuotaExceeded` → bildirim gönderme kuralı (CORE-14 Notifications ile).

## AI-off / AI-on davranışı
AI credit/model/tool erişimi de bu modül üzerinden metrelenir (`docs/14` §3).

## UX one-click journey
Kullanım ekranında tek bakışta kota durumu (`docs/06` panel `Plan ve Kullanım`
bölümü).

## States
Yok.

## Data retention / export
Kullanım metrikleri raporlanabilir, export edilebilir.

## Observability
Kota aşım sıklığı, özellik erişim reddi sıklığı.

## Security / privacy
Kota bypass girişimleri (örn. race condition ile aynı anda çoklu istek) test
edilmeli.

## Accessibility / i18n
Kota uyarı mesajları çok dilli.

## Phase delivery
Stage 1 MVP — temel limitler; Stage 3+ genişletilmiş entitlement (custom
domain, API erişimi vb.).

## Acceptance
Limit-aşımı senaryosunun backend'de gerçekten engellendiğinin testi (yalnız UI
gizleme değil).

## Rollback
Core modül — devre dışı bırakılamaz.

## Open questions
Plan düşürülünce limit-üstü veri davranışı (`docs/16` BIZ-05).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Kullanım/kota tahmini ve "bu ay limit aşımı riski" içgörüsü
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Kullanım metrikleri (iç operasyonel)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Kota zorlaması ve erişim kararı deterministiktir, AI kota vermez/kaldırmaz
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
