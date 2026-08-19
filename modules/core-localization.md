# CORE-08 — Localization

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Gettext PO kanonik kaynağından MO (PHP) ve JSON (React) projeksiyonlarını
üretmek ve locale yönetimini merkezi sağlamak.

## Bounded context
Çeviri metni + locale seçimi altyapısı. Para/tarih formatlaması CORE-12/
CORE-09 ile koordineli ama ayrı sorumluluktadır.

## Owner
Engineering.

## Sınıf
Core.

## Bağımlılıklar
Yok (temel katman).

## Public contracts / events
`TranslationPort::translate(domain, key, locale, params)`; `LocaleChanged`
event'i.

## Tenant isolation
Tenant'ın varsayılan dili ayar olarak saklanır (CORE-06); çeviri metinleri
tenant-bağımsızdır (platform geneli).

## Permissions
`translations.manage` (yalnız Platform Owner/Admin — çeviri kalitesi platform
sorumluluğunda).

## Entitlement / quota
Dil sayısı erişimi plana göre kısıtlanabilir (OPT-04 Multi-language Content ile
ilişkili, ama bu CORE modülü platform UI dilini kapsar).

## ECA hooks
Yok.

## AI-off / AI-on davranışı
AI çeviri (OPT-22) bu modülün üzerine kurulur ama CORE-08 AI'dan bağımsız
çalışır.

## UX one-click journey
Dil seçici tek tıkla değişim, sayfa yeniden yüklenmeden (mümkünse).

## States
Yok.

## Data retention / export
PO dosyaları versiyon kontrollü (kod deposu), export gerektirmez.

## Observability
Eksik çeviri (missing-string) sayısı, fuzzy işaretli string sayısı.

## Security / privacy
Yok (özel risk yok).

## Accessibility / i18n
Bu modülün **kendisi** i18n altyapısıdır (`docs/13`).

## Phase delivery
Stage 1 MVP — altı katalog dizilimi/scaffold'ı (en/tr/de/fr/ar/ru), her
katalog için text-domain wiring ve PO→MO→JSON extraction/projection
pipeline'ının **tamamı** hazır ve entegre; English kaynak katalog
complete/default'tur. Diğer beş dilin (tr/de/fr/ar/ru) **içerik-completeness'i**
(kullanıcının PO üzerinden dolduracağı tam çeviri + plural/context + RTL görsel
completeness) Stage 2'ye bırakılır (`docs/19`) — bu, "Stage 1'de yalnız en+tr
var, diğer katalog yok" **değildir**; pipeline ve altı katalog iskeleti Stage
1'den itibaren çalışır durumdadır, yalnız içerik doluluğu kademelidir.

## Acceptance
Stage 1: PO→MO→JSON projeksiyon zincirinin **tüm altı katalog** için tutarlılık
testi (scaffold+wiring çalışıyor, English içerik complete); RTL **altyapısının**
(yön/token, içerik değil) doğru yüklendiği testi. Stage 2: tr/de/fr/ar/ru
içerik-completeness testi + RTL görsel regresyon testi (`docs/19`).

## Rollback
Core modül — devre dışı bırakılamaz.

## Open questions
Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Eksik çeviri anahtarı için taslak metin önerisi (PO/MO zincirine yalnız onaydan sonra girer)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Kaynak dizeler (işletme içeriği, genelde PII değil)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: PO/MO/JSON projeksiyonu deterministik derleme sürecidir; AI yalnız taslak kaynak metne katkı sağlar
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
