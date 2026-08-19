# Onboarding

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Kayıttan ilk çalışan QR'a kadar kullanıcıyı yönlendirmek ve aktivasyonu
ölçmek.

## Bounded context
Yönlendirme/checklist mantığı. Gerçek varlık oluşturma (workspace, menü, ürün)
ilgili modüllerin sorumluluğunda; Onboarding onları **sıralar**.

## Owner
Product + Engineering.

## Sınıf
Required product.

## Bağımlılıklar
CORE-01, CORE-02, CORE-05, Menu Catalog, Publication, QR Destination.

## Public contracts / events
`OnboardingProgressed`, `ActivationCompleted` event'leri.

## Tenant isolation
Onboarding durumu tenant-scoped.

## Permissions
Yok (özel izin gerektirmez, Owner rolü doğal olarak erişir).

## Entitlement / quota
Yok.

## ECA hooks
`ActivationCompleted` → hoş geldin/tebrik bildirimi tetiklenir.

## AI-off / AI-on davranışı
Demo veri oluşturma seçeneği AI'dan bağımsız (statik demo verisi); AI destekli
"hızlı menü oluşturma" ileri özellik (OPT-21 ile ilişkili, opsiyonel).

## UX one-click journey
11 adımlık akış (`docs/02` §4): e-posta doğrulama → workspace → restoran →
menü → kategori → ürün → önizleme → yayınlama → QR → indirme → test. Kaldığı
yerden devam etme zorunlu. "Restoran" adımı, `modules/core-tenancy.md`
§Business profile contract'ta sahiplenilen alan setinin **minimum required**
alt kümesini (display name, slug, adres, timezone/currency, açılış saatleri)
tek ekranda toplar; **optional** alanlar (external/social/delivery profilleri,
amenity/accessibility) sonraki bir adıma ertelenebilir. Ülkeye/telefon
koduna göre timezone/currency **one-click sensible defaults** ile
ön-doldurulur, kullanıcı isterse değiştirir.

## States
Onboarding: `not_started → in_progress → completed → skipped`.

## Data retention / export
Onboarding reset edilebilir (kullanıcı sıfırdan başlamak isterse).

## Observability
Adım bazlı terk (drop-off) oranı, Time to First QR metriği (`docs/26` §birincil
metrik).

## Security / privacy
Yok (özel risk yok, altta yatan modüllerin güvenliğine tabi).

## Accessibility / i18n
Onboarding akışı WCAG 2.2 AA, çok dilli.

## Phase delivery
Stage 1 MVP — tam kapsam.

## Acceptance
Kaldığı yerden devam etme testi; adım atlama kurallarının doğru uygulandığı
testi; Time to First QR'ın gerçekten ölçüldüğü testi.

## Rollback
Bu modül disable edilirse kullanıcı doğrudan panele yönlendirilir (checklist
kaybolur ama altta yatan modüller çalışmaya devam eder).

## Open questions
Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: İlk kurulum sihirbazında adım-adım rehberlik metni ve alan önerisi (örn. işletme türüne göre örnek kategori seti)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Onboarding form verisi (işletme adı/tür — PII sınırlı)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Hesap/tenant oluşturma kararı deterministiktir, AI hesap açmaz
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
