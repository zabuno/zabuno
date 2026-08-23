# CORE-01 — Identity & Sessions

**Bounded runtime durumu: S1-WP02A'nın dar dikey dilimi (register→
verification-pending→signed/expiring email verification→authenticated
cookie session→logout) yerel çalıştırılabilir bir implementation
candidate'tır — hedefli kanıtla desteklenir, public-promotion RED
(`docs/33` §Final durum). Bu modülün geri kalanı — şifre sıfırlama, geniş
oturum yönetimi (çoklu cihaz/liste/revoke), brute-force tam koruma matrisi,
`locked`/`deactivated` hesap durumları vb. — hâlâ **PLANNING ONLY, şu an
çalıştırılamaz**.**

## Amaç
Kullanıcının var oluşunu ve oturumunu yönetmek: kayıt, doğrulama, giriş/çıkış,
şifre sıfırlama.

## Bounded context
Kullanıcı kimliği ve oturum durumu. Yetki kararı vermez (bkz. CORE-03); yalnız
"bu kim ve şu an oturumu geçerli mi" sorusuna cevap verir.

## Owner
Architecture + Engineering.

## Sınıf
Core.

## Bağımlılıklar
Yok (en temel katman).

## Public contracts / events
`UserRegistered`, `UserVerified`, `UserLoggedIn`, `UserLoggedOut`,
`PasswordResetRequested`, `PasswordResetCompleted`, `AllSessionsRevoked` domain
event'leri; `IdentityPort` interface'i diğer modüllere kullanıcı özet bilgisini
(id, email, display name) salt-okunur sunar.

## Tenant isolation
Kullanıcı hesabı tenant-bağımsızdır (bir kullanıcı birden fazla workspace'e
üye olabilir); tenant-bağlama Membership tablosu üzerinden CORE-02'de kurulur.

## Permissions
Yok (bu modül permission üretmez, yalnız kimliği doğrular).

## Entitlement / quota
Workspace başına maksimum kullanıcı sayısı CORE-04'te uygulanır, burada değil.

## ECA hooks
`UserVerified` event'i onboarding modülünün ECA kurallarını tetikleyebilir
(register edilen taraf onboarding modülüdür, burası yalnız event yayınlar).

## AI-off / AI-on davranışı
AI'dan tamamen bağımsız.

## UX one-click journey
Kayıt formu → tek e-posta doğrulama linki tıklaması → giriş. Hedef: doğrulama
linkine tıklamadan sonra ek adım olmadan panele giriş.

## States
Kullanıcı hesabı: `pending_verification → active → locked → deactivated`.

## Data retention / export
Kullanıcı kendi hesap verisini export edebilir (CORE-15 ile entegre); hesap
silme talebi CORE-16 (Legal Records) rıza kayıtlarıyla koordineli işlenir.

## Observability
Login başarı/başarısızlık oranı, doğrulama e-postası teslim oranı, brute-force
tetiklenme sayısı (`docs/15` §1).

## Security / privacy
BCryptSHA256 benzeri güçlü hash, rate limiting, generic hata mesajları
(`docs/15` §1). Şifre asla düz metin loglanmaz.

## Accessibility / i18n
Login/register/reset formları WCAG 2.2 AA; e-posta şablonları çok dilli
(`docs/13`).

## Phase delivery
Stage 1 MVP — tam kapsam (`docs/18`).

## Acceptance
Kayıt→doğrulama→giriş uçtan uca E2E testi; brute-force koruma testi; şifre
sıfırlama token'ının tek kullanımlık olduğunun testi.

## Rollback
Core modül — devre dışı bırakılamaz (`docs/04` §2).

## Open questions
Google/Apple login, passkey, SMS doğrulama MVP dışı (`docs/01` §7) — M1+ karar
gerektirir.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Şüpheli oturum/giriş denemesi risk açıklaması (kullanıcıya "neden ek doğrulama istendi" açık dilde anlatılır)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Oturum meta verisi (IP/UA/zaman) — kimlik bilgisi/parola asla AI'ya gönderilmez
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Kimlik doğrulama kararının kendisi (login başarı/red) her zaman deterministik kural motorundadır
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
