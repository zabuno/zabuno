# CORE-09 — Taxonomy

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Esnek, kullanıcı tarafından genişletilebilir vocabulary'leri yönetmek (örn.
floor/area isimleri, ürün etiketleri).

## Bounded context
Serbest biçimli listeler. **Typed** kavramlar (currency, permission, lifecycle
state) buraya taşınmaz (`docs/10` §1 kritik ayrım).

## Owner
Engineering.

## Sınıf
Core.

## Bağımlılıklar
CORE-02 (tenant-scoped taxonomy'ler için).

## Public contracts / events
`TaxonomyPort::terms(vocabulary, tenant?)`; `TermCreated` event'i.

## Tenant isolation
Bazı vocabulary'ler platform geneli (örn. alerjen listesi), bazıları
tenant-özel (örn. floor/area isimleri) — vocabulary tanımında işaretlenir.

## Permissions
`taxonomy.manage`.

## Entitlement / quota
Yok.

## ECA hooks
`TermCreated` → ilgili modülün yeni terimi kullanabilir hale gelmesi.

## AI-off / AI-on davranışı
AI'dan bağımsız.

## UX one-click journey
Vocabulary yönetim ekranında tek tık terim ekleme.

## States
Terim: `active → deprecated → archived`.

## Data retention / export
Deprecated terimler mevcut kayıtları bozmaz, yalnız yeni seçimde gizlenir.

## Observability
Vocabulary başına terim sayısı, kullanım sıklığı.

## Security / privacy
Yok.

## Accessibility / i18n
Terimler çevrilebilir (CORE-08 ile entegre).

## Phase delivery
Stage 1 MVP — temel (alerjen listesi gibi sabit vocabulary'ler); Stage 2'de
tam esnek yönetim.

## Acceptance
Typed-kavram sızıntısı testi (örn. currency kodu yanlışlıkla taxonomy'ye
eklenmeye çalışılırsa reddedilmeli — kural doğrulaması).

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
- **Optional AI use case(ler)**: Duplicate/yakın-eşleşen vocabulary terimi tespiti ve birleştirme önerisi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Taksonomi terim listesi (iç operasyonel)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Terim birleştirme/silme kararı insan onayı gerektirir, AI otomatik birleştirmez
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
