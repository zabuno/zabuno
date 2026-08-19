# OPT-22 — AI Translation

**PLANNING ONLY. Şu an çalıştırılamaz.**

**Amaç**: Multi-language Content (OPT-04) için AI destekli çeviri önerisi
sağlamak.
**Bounded context**: AI Platform'u kullanarak çeviri taslağı üretir; son onay
her zaman insanda (`docs/13` ile uyumlu — PO/MO/JSON zincirine yalnız
**onaylanmış** çeviri girer).
**Owner**: Engineering + AI Platform sahibi. **Sınıf**: Optional (M2).
**Bağımlılıklar**: AI Platform, Multi-language Content (OPT-04).
**Public contracts/events**: `AITranslationSuggested`, `AITranslationApproved`.
**Tenant isolation**: AI çağrısı tenant-izole.
**Permissions**: `content.translate.ai`.
**Entitlement**: AI credit kotası.
**ECA hooks**: Yeni dil eklendiğinde otomatik taslak çeviri önerisi
tetiklenebilir (öneri, otomatik yayın değil).
**AI-off/on**: AI-off'ta OPT-04 manuel çeviriyle çalışmaya devam eder.
**UX**: Çeviri ekranında "AI öner" butonu, öneri düzenlenebilir metin kutusunda
gösterilir.
**States**: Öneri: `suggested → edited/accepted → published`.
**Retention**: OPT-04 ile aynı.
**Observability**: Öneri kabul oranı (düzenlenmeden kabul edilme sıklığı).
**Security**: Yok.
**A11y/i18n**: Bu modülün kendisi i18n'in AI-destekli tarafıdır.
**Phase delivery**: Growth Stage.
**Acceptance**: Onaylanmamış AI çevirisinin public menüde **görünmediğinin**
testi.
**Rollback**: Disable edilirse yalnız manuel çeviri kalır.
**Open questions**: Çok-dilli çeviri kalitesi için eval seti tanımlanmadı
(`docs/16` AI-03).

## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — OPT-04 manuel çeviri akışı her
  zaman kesintisiz çalışır.
- **ai_posture**: assistive.
- **Optional AI use case(ler)**: Yeni dil eklendiğinde taslak çeviri önerisi.
- **AI-off / no-credit deterministic path**: OPT-04 manuel çeviri akışı
  kesintisiz çalışır; "AI öner" butonu görünmez, mevcut içerik/taslak
  kaybolmaz.
- **Data classification**: Çevrilecek metin — işletme içeriği (menü/ürün
  metni); PII içermesi beklenmez, içerirse redaction politikası uygulanır.
- **Allowed tools/side effects**: Yalnız çeviri taslağı üretimi
  (`AITranslationSuggested`); PO/MO/JSON zincirine yazma yetkisi yok.
- **Forbidden authority (final-authority)**: Yayına alma (publish) kararı bu modülde değil,
  Publication'da; AI onaylanmamış çeviriyi asla public menüde göstermez.
- **Human approval**: Zorunlu — öneri düzenlenebilir kutuda gösterilir,
  kabul/düzenleme insan eylemidir.
- **Feature policy**: feature="content_translation" × model × account ×
  policy × tenant/residency (`ai-provider-account-vault`).
- **Budget/credit behavior**: reserve→invoke→debit; reddedilen öneri
  release/refund edilir.
- **Eval/audit**: Öneri kabul oranı (düzenlenmeden kabul sıklığı) izlenir;
  ayrı çeviri kalite eval seti tanımlı değil (`docs/16` AI-03).
- **Phase**: Growth Stage.
