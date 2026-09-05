# OPT-09 — Custom Domain

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

**Amaç**: Tenant'ın kendi domainini (örn. menu.restoranadi.com) public menüye
bağlamasını sağlamak.
**Bounded context**: Publication/QR Destination'ın public erişim katmanına
domain-routing eklemesi.
**Owner**: Engineering. **Sınıf**: Optional (Growth edition).
**Bağımlılıklar**: Publication, CORE-06 (SSL sertifika secret'ları).
**Public contracts/events**: `CustomDomainVerified`, `CustomDomainFailed`.
**Tenant isolation**: Domain→tenant eşlemesi tek yönlü ve doğrulanmış olmalı
(başka tenant'ın domainini iddia edememe).
**Permissions**: `domain.manage`.
**Entitlement**: Growth+ edition.
**ECA hooks**: `CustomDomainFailed` → bildirim.
**AI-off/on**: AI'dan bağımsız.
**UX**: DNS doğrulama adımlarının tek ekranda gösterimi (TXT/CNAME kaydı).
**States**: `pending_verification → verified → active → failed`.
**Retention**: Domain kaydı tenant silinince temizlenir.
**Observability**: SSL sertifika yenileme başarı oranı.
**Security**: Domain hijacking önleme (doğrulama olmadan aktivasyon yok).
**A11y/i18n**: Kurulum talimatları çok dilli.
**Phase delivery**: Stage 5 Growth.
**Acceptance**: Doğrulanmamış domainin aktive edilemediğinin testi.
**Rollback**: Custom domain kaybedilirse platform domainine (`q.example.com`)
otomatik fallback (`docs/16` §B QR bilinmeyenleri ile ilişkili).
**Open questions**: Custom domain kullanan müşteri ayrılırsa QR ne olacak
(`docs/16` §B).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: DNS/domain doğrulama hatası açıklaması ve düzeltme önerisi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: DNS meta verisi (PII değil)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Domain bağlama/doğrulama kararı deterministik teknik kontroldür
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
