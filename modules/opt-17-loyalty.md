# OPT-17 — Loyalty

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

**Amaç**: Müşteri sadakat puanı/ödül programı sağlamak.
**Bounded context**: Mini CRM'in Contact modelini kullanır, kendi puan/ödül
veri modelini ekler.
**Owner**: Product + Engineering. **Sınıf**: Optional (M2).
**Bağımlılıklar**: Mini CRM, Online Ordering (OPT-14, puan kazanma tetikleyicisi
için opsiyonel).
**Public contracts/events**: `PointsEarned`, `RewardRedeemed`.
**Tenant isolation**: Aynı.
**Permissions**: `loyalty.manage`.
**Entitlement**: M2 edition.
**ECA hooks**: Sipariş tamamlandığında puan kazanma kuralı.
**AI-off/on**: AI kişiselleştirilmiş ödül önerebilir; puan hesaplaması
deterministiktir (CORE-12 Money benzeri hassasiyet).
**UX**: Müşteri profilinde tek bakışta puan durumu.
**States**: Ödül: `available → redeemed → expired`.
**Retention**: Puan geçmişi saklanır.
**Observability**: Program katılım oranı.
**Security**: Puan manipülasyonu (race condition ile çift kazanma) önlenmeli.
**A11y/i18n**: Standart.
**Phase delivery**: Growth Stage.
**Acceptance**: Aynı siparişten çift puan kazanılamadığının testi (idempotency).
**Rollback**: Disable edilirse mevcut puanlar dondurulur, silinmez.
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Ödül seviyesi/kullanım içgörüsü
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Puan/işlem meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Puan ekleme/kullanma işlemi deterministik kural motorundadır
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
