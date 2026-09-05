# CORE-06 — Settings, Secrets & Integrations

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
Platform/tenant/kullanıcı ayar hiyerarşisini ve entegrasyon secret'larını
güvenli biçimde yönetmek.

## Bounded context
Ayar okuma/yazma ve secret saklama. İş mantığı içermez.

## Owner
Technical Operator + Security.

## Sınıf
Core.

## Bağımlılıklar
CORE-02 (tenant-scope ayarlar için).

## Public contracts / events
`SettingsPort::get/set(scope, key)`; `SecretRotated` event'i.

## Tenant isolation
Tenant ayarları başka tenant'a sızmaz; platform ayarları yalnız Platform Owner.

## Permissions
`settings.manage.platform`, `settings.manage.tenant`.

## Entitlement / quota
Yok.

## ECA hooks
`SecretRotated` → ilgili entegrasyonun yeniden doğrulanması tetiklenebilir.

## AI-off / AI-on davranışı
AI provider API key'leri bu modül üzerinden saklanır (`docs/14` §3).

## UX one-click journey
Ayar hiyerarşisi (Platform > Tenant > Kullanıcı) tek ekranda override
görünürlüğüyle sunulur.

## States
Yok.

## Data retention / export
Secret'lar export edilmez (yalnız var/yok bilgisi gösterilir).

## Observability
Secret rotasyon sıklığı, yapılandırma hatası oranı.

## Security / privacy
Secret'lar encrypted-at-rest saklanır; hiçbir log'a düz metin yazılmaz.

## Accessibility / i18n
Ayar ekranları WCAG 2.2 AA.

## Phase delivery
Stage 1 MVP — temel ayarlar; entegrasyon secret yönetimi genişleyerek devam
eder.

## Acceptance
Secret'ın loglara sızmadığının testi; tenant ayar izolasyonu testi.

## Rollback
Core modül — devre dışı bırakılamaz.

## Open questions
Yok (bu modül görece düşük belirsizlikli).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Ayar/konfigürasyon drift açıklaması (yalnız anahtar adı/rotasyon yaşı — asla secret değeri)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Ayar meta verisi (secret DEĞERİ hiçbir koşulda AI'ya gönderilmez)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Secret depolama/erişim kararı deterministiktir; AI secret değerini asla görmez/işlemez
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
