# OPT-23 — AI Product Description

**PLANNING ONLY. Şu an çalıştırılamaz.**

**Amaç**: Ürün açıklaması taslağı üretmek (AI destekli metin önerisi).
**Bounded context**: AI Platform'u kullanır, Menu Catalog'un açıklama alanına
öneri sunar.
**Owner**: Engineering + AI Platform sahibi. **Sınıf**: Optional (M2, ama
kavramsal olarak en erken devreye girebilecek AI özelliği — düşük risk).
**Bağımlılıklar**: AI Platform, Menu Catalog.
**Public contracts/events**: `AIDescriptionSuggested`.
**Tenant isolation**: AI çağrısı tenant-izole.
**Permissions**: `product.description.ai`.
**Entitlement**: AI credit kotası.
**ECA hooks**: Yok.
**AI-off/on**: AI-off'ta ürün açıklaması alanı boş bırakılabilir/manuel
girilir — hiçbir zorunluluk yok.
**UX**: Ürün formunda "AI ile öner" butonu, öneri metin kutusuna doldurulur,
kullanıcı düzenleyip kaydeder.
**States**: Öneri: `suggested → edited/accepted/discarded`.
**Retention**: Kabul edilmeyen öneriler saklanmaz.
**Observability**: Öneri kabul oranı.
**Security**: Alerjen/içerik bilgisi AI tarafından **uydurulamaz** — AI yalnız
pazarlama diline dair açıklama önerir, alerjen/besin verisi her zaman
kullanıcı girişi (yasal sorumluluk nedeniyle, `docs/04` MOD-R03 alerjen
kararı).
**A11y/i18n**: Standart.
**Phase delivery**: Post-MVP/Growth.
**Acceptance**: AI önerisinin alerjen alanına asla otomatik yazılmadığının
testi (yalnız açıklama alanına sınırlı).
**Rollback**: Disable edilirse manuel açıklama girişi devam eder.
**Open questions**: Yok.

## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — açıklama alanı manuel doldurulabilir
  veya boş bırakılabilir, hiçbir zorunluluk yok.
- **ai_posture**: assistive.
- **Optional AI use case(ler)**: Pazarlama diliyle ürün açıklaması taslağı.
- **AI-off / no-credit deterministic path**: "AI ile öner" butonu görünmez;
  açıklama alanı manuel doldurulur veya boş bırakılabilir — hiçbir zorunluluk
  yok, veri kaybı olmaz.
- **Data classification**: Ürün adı/kategori — işletme içeriği; **alerjen/
  besin verisi bu modüle asla girdi olarak verilmez ve bu modül tarafından
  asla üretilmez** (yasal sorumluluk, `docs/04` MOD-R03).
- **Allowed tools/side effects**: Yalnız açıklama metni taslağı
  (`AIDescriptionSuggested`); alerjen/fiyat/görünürlük alanına yazma yetkisi
  yok.
- **Forbidden authority (final-authority)**: Alerjen/besin beyanı, fiyat, yayın kararı — bu
  modül yalnız pazarlama açıklama metnine sınırlıdır.
- **Human approval**: Zorunlu — öneri kullanıcı tarafından düzenlenip
  kaydedilir.
- **Feature policy**: feature="product_description" × model × account ×
  policy × tenant/residency (`ai-provider-account-vault`).
- **Budget/credit behavior**: reserve→invoke→debit; kullanılmayan/atılan
  öneri release/refund edilir.
- **Eval/audit**: Öneri kabul oranı izlenir; alerjen alanına asla otomatik
  yazılmadığının testi `Acceptance` alanında tanımlı.
- **Phase**: Post-MVP/Growth.
