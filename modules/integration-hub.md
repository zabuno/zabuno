# Integration Hub

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Webhook ve API client kaydını merkezi olarak yönetmek.

## Bounded context
Dış entegrasyon kayıt/kimlik doğrulama altyapısı. Belirli bir entegrasyonun
(Iyzico, GTM vb.) iş mantığı ilgili modülde.

## Owner
Engineering.

## Sınıf
Required product.

## Bağımlılıklar
CORE-06 (secret'lar), CORE-07 (audit).

## Public contracts / events
`WebhookRegistered`, `APIClientCreated`, `WebhookDeliveryFailed` event'leri.

## Tenant isolation
API client'lar tenant-scoped.

## Permissions
`integration.manage`.

## Entitlement / quota
Developer API/Webhooks (OPT-26) plana bağlı.

## ECA hooks
`WebhookDeliveryFailed` → retry kuralı.

## AI-off / AI-on davranışı
MCP server registry bu modülün bir uzantısı olarak konumlanabilir (`docs/14`
§6) ama bu modülün kendisi AI'dan bağımsız çalışır.

## UX one-click journey
API client oluşturma tek ekranda (key üretimi + scope seçimi).

## States
API client: `active → revoked`.

## Data retention / export
Webhook delivery logları saklanır (hata ayıklama için).

## Observability
Webhook başarı/retry oranı, API client kullanım hacmi.

## Security / privacy
API key rotasyonu, scope-limited erişim (least privilege).

## Accessibility / i18n
Yönetim ekranı WCAG 2.2 AA.

## Phase delivery
Stage 6 Enterprise — genişletilmiş API/webhook (`docs/23`); temel webhook
altyapısı (Iyzico için) Stage 1 MVP'de zaten gereklidir (o kullanım Iyzico
Payment modülü üzerinden, bu modül M1+'ta genel amaçlı hale gelir).

## Acceptance
Revoke edilen API key'in anında geçersiz olduğunun testi.

## Rollback
Disable edilirse üçüncü taraf entegrasyonlar durur, tenant'ın kendi verisi
etkilenmez.

## Open questions
GA4/Yandex Metrica inbound (salt-okunur) raporlama bağlantısının kapsam/
quota/attribution belirsizliği (`docs/16` ANL-03, `docs/12` §5a) — bağlantının
kendisi (OAuth/secret custody) bu modülde yaşar, veri tüketimi
`modules/analytics-consent-tagging.md`'dedir.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: agentic_guarded
- **Optional AI use case(ler)**: Webhook/API eşleme önerisi ve sandbox'ta test çağrısı çalıştırma (üretim veri/isteğine dokunmadan)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: API şema/eşleme meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Üretim webhook/API kaydının etkinleştirilmesi insan onayı gerektirir
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
