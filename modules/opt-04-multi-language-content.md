# OPT-04 — Multi-language Content

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

**Amaç**: Restoran içeriğinin (menü/ürün adları, açıklamalar) birden fazla dilde
girilmesini sağlamak.
**Bounded context**: İçerik çeviri altyapısı CORE-08'in üzerine kurulur ama
CORE-08 **platform UI**'ı, bu modül **kullanıcı içeriğini** çevirir — ayrı
sorumluluk.
**Owner**: Product + Engineering. **Sınıf**: Optional (M1).
**Bağımlılıklar**: CORE-08, Menu Catalog.
**Public contracts/events**: `ContentTranslated`.
**Tenant isolation**: İçerik çevirileri tenant-scoped (kullanıcı verisi).
**Permissions**: `content.translate.manage`.
**Entitlement**: Dil sayısı plana bağlı.
**ECA hooks**: Yeni dil eklendiğinde eksik çeviri uyarısı.
**AI-off/on**: AI çeviri önerebilir (OPT-22 ile ilişkili) ama son onay
kullanıcıda.
**UX**: Dil sekmeleri arasında tek tık geçiş, eksik çeviri göstergesi.
**States**: Çeviri: `missing → draft → reviewed → published`.
**Retention**: Menu Catalog ile aynı.
**Observability**: Dil başına çeviri tamlık yüzdesi.
**Security**: Yok. **A11y/i18n**: Bu modülün kendisi i18n'in kullanıcı-içerik
tarafıdır; RTL diller için özel test gerekir.
**Phase delivery**: Stage 2 Post-MVP.
**Acceptance**: Eksik çevirinin public menüde fallback dile düştüğünün testi
(boş içerik gösterilmez).
**Rollback**: Disable edilirse yalnız varsayılan dil gösterilir.
**Open questions**: Her dil ayrı kayıt mı çeviri tablosu mu — çeviri tablosu
modeli seçilmiştir (kaynak doküman sorusu, `docs/00` §4 ile hizalı karar).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Eksik dil kapsama analizi ("şu N ürün şu dilde çevrilmemiş")
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Çeviri kapsama meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Gerçek çeviri üretimi opt-22'nindir, bu modül yalnız kapsam açıklar
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
