# OPT-21 — AI Menu Import

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

**Amaç**: Fotoğraf/PDF menüden AI ile otomatik ürün/kategori/fiyat çıkarımı
yapmak.
**Bounded context**: AI Platform'u kullanarak Menu Catalog'a taslak veri
önerir; **hiçbir veri AI onayı olmadan doğrudan kaydedilmez**.
**Owner**: Engineering + AI Platform sahibi. **Sınıf**: Optional (M2).
**Bağımlılıklar**: AI Platform, Menu Catalog, CORE-13 (görsel işleme).
**Public contracts/events**: `AIImportDraftGenerated`, `AIImportReviewed`.
**Tenant isolation**: AI çağrısı tenant-izole (`docs/14` §3).
**Permissions**: `menu.import.ai`.
**Entitlement**: AI credit kotasına tabi (CORE-04).
**ECA hooks**: Yok.
**AI-off/on**: **AI-off'ta bu modül tamamen görünmez** — manuel giriş (Menu
Catalog) her zaman çalışır (`docs/01` §3 temel ilke).
**UX**: Fotoğraf yükle → AI taslak çıkarır → kullanıcı satır satır onaylar/
düzeltir → kaydet. Hiçbir adım otomatik publish yapmaz.
**States**: Taslak: `processing → ready_for_review → approved/rejected`.
**Retention**: Taslak reddedilirse silinir; kaynak görsel CORE-13 politikasına
tabi.
**Observability**: AI çıkarım doğruluk oranı (kullanıcı düzeltme sıklığı ile
ölçülür).
**Security**: Yüklenen görsel CORE-13 güvenlik pipeline'ından geçer.
**A11y/i18n**: Review ekranı WCAG 2.2 AA.
**Phase delivery**: Post-MVP/Growth (AI Platform'a bağlı, en erken Stage 2).
**Acceptance**: Onaylanmamış hiçbir AI taslağının Menu Catalog'a
yazılmadığının testi.
**Rollback**: Disable edilirse yalnız manuel giriş kalır (veri kaybı yok, bu
modül zaten yalnız taslak üretiyordu).
**Open questions**: Yok.

## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — Menu Catalog'a manuel giriş her
  zaman birincil ve tek gerekli yoldur.
- **ai_posture**: assistive.
- **Optional AI use case(ler)**: Fotoğraf/PDF → ürün/kategori/fiyat taslak
  çıkarımı (`modules/ai-platform.md` üzerinden invoke edilir).
- **AI-off / no-credit deterministic path**: Modül tamamen görünmez olur;
  Menu Catalog'a manuel giriş kesintisiz çalışır; yüklenmiş fotoğraf/PDF
  kullanıcının cihazında/CORE-13'te korunur, kayıp olmaz.
- **Data classification**: Yüklenen görsel/PDF — işletme içeriği (kişisel veri
  değil, ama telif/marka duyarlı olabilir); CORE-13 güvenlik pipeline'ından
  geçer.
- **Allowed tools/side effects**: Yalnız taslak üretimi (`AIImportDraftGenerated`);
  hiçbir doğrudan Menu Catalog yazma yetkisi yoktur.
- **Forbidden authority (final-authority)**: Publish/delete/purge, fiyat finalitesi (CORE-12
  ile ilişkili görünse de bu modül yalnız taslak fiyat *önerir*, gerçek fiyat
  kaydı insan onayından geçer) ve tenant isolation kararı bu modüle
  devredilmez.
- **Human approval**: Zorunlu — taslağın her satırı kullanıcı tarafından
  onaylanmadan Menu Catalog'a yazılmaz (`docs/01` §3).
- **Feature policy**: `ai-provider-account-vault` üzerinden çözülen
  feature="menu_import" × model × account × policy × tenant/residency satırı.
- **Budget/credit behavior**: Her import işlemi bir reserve→invoke→debit
  döngüsüdür; reddedilen/iptal edilen taslaklar release/refund edilir.
- **Eval/audit**: Çıkarım doğruluk oranı (`Observability` alanı) eval
  kaynağıdır; ayrı bir regresyon eval seti henüz tanımlı değil (`docs/16`'ya
  açık madde).
- **Phase**: Post-MVP/Growth (Stage 2+, AI Platform'a bağlı).
