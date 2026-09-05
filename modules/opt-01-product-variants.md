# OPT-01 — Product Variants

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

**Amaç**: Bir ürünün boyut/tür varyantlarını (küçük/orta/büyük, sade/şekerli
vb.) fiyat farkıyla yönetmek.
**Bounded context**: Menu Catalog'un üzerine kurulur, Product/MenuItem
ilişkisini bozmaz — varyant kendi başına bir MenuItem fiyat kırılımıdır.
**Owner**: Product + Engineering. **Sınıf**: Optional (M1).
**Bağımlılıklar**: Menu Catalog.
**Public contracts/events**: `VariantCreated`, `VariantPriceChanged`.
**Tenant isolation**: Menu Catalog ile aynı.
**Permissions**: `product.variant.manage`.
**Entitlement**: Plan'a bağlı enable/disable.
**ECA hooks**: Varyant stok bitince otomatik gizleme.
**AI-off/on**: AI'dan bağımsız.
**UX**: Varyant ekleme ürün formunda tek satır ekleme.
**States**: Menu Catalog Product states ile aynı.
**Retention**: Menu Catalog ile aynı.
**Observability**: Varyant başına satış/görünürlük istatistiği (Advanced
Analytics ile birleşebilir).
**Security**: Yok. **A11y/i18n**: Varyant adları çevrilebilir.
**Phase delivery**: Stage 2 Post-MVP (`docs/19`).
**Acceptance**: Varyant fiyatının MenuItem fiyatını doğru override ettiğinin
testi.
**Rollback**: Disable edilirse varyantlar gizlenir, temel ürün görünür kalır.
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Ürün adına göre olası varyant seti önerisi (beden/porsiyon vb.)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Ürün içeriği
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Kayıt yazma insan onayı gerektirir
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
