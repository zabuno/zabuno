# Mini CRM

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Tenant-yerel contact/consent/timeline/segment yönetimi sağlamak.

## Bounded context
Tenant'ın kendi müşteri/lead verisi. Platform-seviyeli CRM (Sales/CRM
Operator rolü) **ayrı veri alanıdır** (`docs/11` §3).

## Owner
Product + Engineering.

## Sınıf
Required product.

## Bağımlılıklar
CORE-02, CORE-11 (ECA event register — örn. "yeni contact eklendiğinde
segment'e otomatik ekle").

## Public contracts / events
`ContactCreated`, `SegmentUpdated`, `TaskCompleted` event'leri.

## Tenant isolation
Tam tenant-scoped, platform CRM ile karışmaz.

## Permissions
`crm.manage`.

## Entitlement / quota
Genişletilmiş CRM (OPT-18) plana bağlı.

## ECA hooks
Contact/consent event'leri ECA motoruna register edilir.

## AI-off / AI-on davranışı
AI segment önerisi/özet üretebilir; contact verisi düzenleme her zaman
insan onaylı.

## UX one-click journey
Timeline'da tek bakışta müşteri geçmişi.

## States
Contact: `active → inactive → archived`.

## Data retention / export
Contact verisi export edilebilir (CORE-15).

## Observability
Segment büyüklüğü trendi.

## Security / privacy
Consent kaydı CORE-16 ile entegre.

## Accessibility / i18n
CRM ekranı WCAG 2.2 AA.

## Phase delivery
Stage 2 Post-MVP — temel kapsam (`docs/19`); Stage 5'te genişletilmiş (OPT-18).

## Acceptance
Tenant/platform CRM veri ayrımının doğrulandığı testi.

## Rollback
Disable edilirse contact verisi arşivlenir, silinmez.

## Open questions
Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Contact/segment özetleme ve "sıradaki en iyi eylem" önerisi (otomatik iletişim göndermez)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Contact meta verisi + consent durumu (PII, redaction politikasına tabi)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: İletişim gönderme/consent kaydı kararı AI'ya devredilmez
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
