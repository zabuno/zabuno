# CORE-14 — Notifications

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
E-posta ve uygulama içi bildirim altyapısını sağlamak (SMS adaptörleri de bu
modül altında konumlanır, `docs/11`).

## Bounded context
Bildirim gönderim/teslim altyapısı. Bildirim *içeriği* ilgili modül sahibinde
(örn. "davet e-postası" Team modülünün, altyapı burada).

## Owner
Engineering.

## Sınıf
Core.

## Bağımlılıklar
CORE-06 (SMTP/Mailgun/SMS secret'ları için).

## Public contracts / events
`NotificationPort::send(channel, template, recipient, params)`;
`NotificationSent`, `NotificationFailed`, `NotificationBounced` event'leri.

## Tenant isolation
Bildirim gönderim geçmişi tenant-scoped.

## Permissions
`notifications.manage` (şablon yönetimi için).

## Entitlement / quota
SMS gönderim kotası plana göre kısıtlanabilir (maliyet nedeniyle).

## ECA hooks
Herhangi bir event bildirim tetikleyebilir (en yaygın ECA action tipi).

## AI-off / AI-on davranışı
AI, bildirim metnini **önerebilir**, gönderim öncesi onay gerekir (transactional
bildirimler için AI önerisi kullanılmaz, yalnız marketing içerik önerilerinde).

## UX one-click journey
Bildirim tercihleri ekranında tek tık açma/kapama.

## States
Bildirim: `queued → sent → delivered → bounced/failed`.

## Data retention / export
Bildirim geçmişi retention politikasına tabi.

## Observability
Teslim oranı, bounce/complaint oranı, health check durumu (`docs/11` §1).

## Security / privacy
Suppression list yönetimi (şikayet edenlere tekrar gönderim engeli).

## Accessibility / i18n
Bildirim şablonları çok dilli, e-posta responsive+erişilebilir HTML.

## Phase delivery
Stage 1 MVP — e-posta (native+health check); Stage 1/2'de SMTP/Mailgun/SMS tam
adapter.

## Acceptance
Health check + delivery test'in gerçekten hata yakaladığının testi (`docs/11`
§1 zorunluluğu).

## Rollback
Core modül — devre dışı bırakılamaz.

## Open questions
SMS sağlayıcı maliyet karşılaştırması yapılmadı (`docs/16` COM-02).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Bildirim şablonu taslak metni önerisi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Şablon içeriği (iş içeriği, PII değişkeni yer tutucu olarak kalır)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Gönderim tetikleme kararı deterministik kural motorundadır (CORE-11), AI göndermez
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
