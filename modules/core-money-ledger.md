# CORE-12 — Money / Ledger

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Tüm parasal işlemleri float kullanmadan, deterministik olarak hesaplamak ve
immutable double-entry ledger'da kaydetmek.

## Bounded context
Para hesaplama + ledger kaydı. Ödeme sağlayıcı entegrasyonu (Iyzico) ayrı bir
modüldür (`modules/iyzico-payment.md`), bu modül onun **altyapısıdır**.

## Owner
Finance Operator + Engineering.

## Sınıf
Core.

## Bağımlılıklar
Yok (temel katman, diğer para-ilişkili modüller buna bağımlı).

## Public contracts / events
`MoneyPort` (brick/money value object'leri); `LedgerPort::record(entry)`;
`LedgerEntryRecorded` event'i.

## Tenant isolation
Ledger kayıtları tenant-scoped ama immutable + audit'e tabi.

## Permissions
`ledger.view` (Finance Operator, salt-okunur çoğu rol için).

## Entitlement / quota
Yok.

## ECA hooks
Yok (finansal işlemler ECA otomasyonuna açılmaz — bilinçli sınır, yanlışlıkla
tetiklenen para hareketi riskini önlemek için).

## AI-off / AI-on davranışı
AI'nın para hesaplamasına doğrudan müdahalesi **yasaktır** — yalnız
raporlama/özet üretebilir.

## UX one-click journey
Yok (arka plan altyapısı).

## States
Ledger entry: yok (immutable, tek durum).

## Data retention / export
Ledger asla silinmez; export edilebilir (muhasebe/denetim için).

## Observability
Reconciliation uyuşmazlık oranı.

## Security / privacy
Property-based money testleri zorunlu (`docs/09` §1, `docs/27` §5).

## Accessibility / i18n
Para formatlaması locale'e duyarlı (CORE-08 ile koordineli).

## Phase delivery
Stage 1 MVP — temel Money value object + basit ledger; Stage 3 GTM'de tam
reconciliation.

## Acceptance
Yuvarlama/proration deterministik testi; ledger immutability testi.

## Rollback
Core modül — devre dışı bırakılamaz.

## Open questions
eloquent-ifrs R&D candidate, henüz doğrulanmadı (`docs/09` §2).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Ledger anomali tespiti ve açıklaması (örn. "bu reconciliation farkı şu üç işlemden kaynaklanıyor olabilir")
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Ledger meta verisi (tutar/tarih, kart bilgisi asla değil)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Para hesaplama ve ledger kaydı finalitesi AI'ya kesinlikle kapalıdır — yalnız raporlama/açıklama
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
