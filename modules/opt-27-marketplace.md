# OPT-27 — Marketplace

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

**Amaç**: Üçüncü taraf eklenti/tema/entegrasyon geliştiricilerinin ürünlerini
platforma sunabileceği bir pazar yeri sağlamak.
**Bounded context**: Module Registry'nin (CORE-05) **dış geliştiriciye açık**
uzantısı — üçüncü taraf modüller aynı manifest sözleşmesine uyar.
**Owner**: Product + Engineering + Security (üçüncü taraf kod inceleme).
**Sınıf**: Optional (Growth+ erken aşama).
**Bağımlılıklar**: CORE-05, CORE-03 (üçüncü taraf modülün yetki sınırı).
**Public contracts/events**: `MarketplaceModuleSubmitted`,
`MarketplaceModuleApproved`, `MarketplaceModuleInstalled`.
**Tenant isolation**: Üçüncü taraf modül de CORE-02/CORE-03 sınırlarına tabi —
istisna yok.
**Permissions**: `marketplace.manage`, `marketplace.install`.
**Entitlement**: Plan'a göre marketplace erişimi kısıtlanabilir.
**ECA hooks**: Üçüncü taraf modül kendi event/action'larını register edebilir
(allowlist ile sınırlı, `docs/14` §6 ile aynı prensip).
**AI-off/on**: AI'dan bağımsız.
**UX**: Marketplace listesi, tek tık install (onay ekranıyla — hangi izinleri
istediği gösterilir).
**States**: Marketplace modülü: `submitted → review → approved/rejected →
published`.
**Retention**: Reddedilen gönderimler geri bildirimle saklanır.
**Observability**: Install sayısı, üçüncü taraf modül hata oranı.
**Security**: Üçüncü taraf kod inceleme süreci zorunlu — otomatik yayın yok.
**A11y/i18n**: Marketplace UI WCAG 2.2 AA.
**Phase delivery**: Growth Stage (erken aşama, `docs/22`).
**Acceptance**: Üçüncü taraf modülün CORE sınırlarını aşamadığının (sandbox
testi ile) doğrulanması.
**Rollback**: Sorunlu bir marketplace modülü tüm tenantlarda anlık disable
edilebilir.
**Open questions**: Gelir paylaşım modeli tanımlanmadı.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Listeleme kalite/eksik alan önerisi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Listeleme içeriği
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Listeleme onay/yayın kararı insan eylemidir
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
