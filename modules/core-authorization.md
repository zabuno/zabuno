# CORE-03 — Authorization

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Tek bir Policy Decision Point üzerinden panel/module/record seviyeli, deny-by-
default yetki kararı vermek.

## Bounded context
"Bu kullanıcı bu eylemi bu kaynakta yapabilir mi" sorusu. Kimlik (CORE-01) ve
tenant (CORE-02) çözülmüş olmalı; bu modül onların üzerine kurulur.

## Owner
Architecture + Engineering + Security (birlikte).

## Sınıf
Core.

## Bağımlılıklar
CORE-01, CORE-02.

## Public contracts / events
`AuthorizationPort::can(user, action, resource)` — tüm modüller bu port
üzerinden yetki sorar; doğrudan rol/izin tablosu sorgulamaz. `AccessDenied`
event'i audit'e (CORE-07) yazılır.

## Tenant isolation
Her yetki kararı tenant scope'u içerir — cross-tenant erişim yapısal olarak
imkansız olmalı (`docs/05` §2).

## Permissions
Bu modül permission **tanımlamaz**, diğer modüllerin tanımladığı permission
string'lerini (`menu.view`, `qr.create` vb., `docs/02` §5 desenli) değerlendirir.

## Entitlement / quota
CORE-04 ile ayrı ama işbirlikli: Authorization "yapabilir mi", Entitlements
"kotası var mı" sorularını cevaplar — ikisi de geçmeli.

## ECA hooks
Yetki reddi event'leri ECA motoruna (CORE-11) register edilebilir (örn.
"tekrarlayan yetki reddi → güvenlik uyarısı").

## AI-off / AI-on davranışı
AI'dan bağımsız — AI tool-allowlist kararları da bu PDP üzerinden geçer.

## UX one-click journey
Yok (bu modül arka plan altyapısıdır, doğrudan UI'ı yoktur — yalnız affordance
sinyali üretir, `docs/06` §5).

## States
Yok.

## Data retention / export
Yetki kararı logları CORE-07 audit trail'inde saklanır.

## Observability
Reddedilen istek oranı, PDP karar süresi (performans, `docs/16` AUTH-01).

## Security / privacy
Explainable decision zorunlu — "neden reddedildi" sorgulanabilir olmalı
(`docs/05` §2).

## Accessibility / i18n
Yetki hata mesajları çok dilli ve anlaşılır (jenerik "403" değil, bağlama uygun
mesaj — ama saldırgana bilgi sızdırmayacak şekilde).

## Phase delivery
Stage 1 MVP — RBAC baseline; ABAC MVP'de kısmi; ReBAC/OpenFGA Stage 6
Enterprise'da değerlendirilir (`docs/05` §2).

## Acceptance
Auth policy matrix testi (`docs/27` §5) — her rol × her kaynak kombinasyonu
otomatik test edilir.

## Rollback
Core modül — devre dışı bırakılamaz.

## Open questions
PDP performans etkisi ölçülmedi (`docs/16` AUTH-01); OpenFGA ne zaman devreye
girer, henüz karar yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Yetki politikası taslağı önerisi ve "bu istek neden reddedildi" açıklaması (simülasyon modunda, canlı PDP kararını etkilemez)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Rol/izin meta verisi (PII değil)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: PDP'nin canlı karar/red kararı her zaman deterministiktir; AI yalnız taslak/açıklama üretir, hiçbir isteği bizzat onaylamaz/reddetmez
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
