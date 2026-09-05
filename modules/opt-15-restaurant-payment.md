# OPT-15 — Restaurant Payment

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

**Amaç**: Müşterinin QR üzerinden hesabını ödemesini sağlamak (masada ödeme).
**Bounded context**: Bu, platformun **kendi** SaaS aboneliği ödemesinden
(Iyzico Payment modülü) tamamen **ayrı** bir akıştır — burada ödeyen
restoranın kendi müşterisidir.
**Owner**: Finance Operator + Engineering + Security. **Sınıf**: Optional (M2).
**Bağımlılıklar**: Online Ordering (OPT-14), CORE-12 (Money).
**Public contracts/events**: `CustomerPaymentSucceeded`, `CustomerPaymentFailed`.
**Tenant isolation**: Ödeme kaydı restoranın (tenant'ın) kendi hesabına gider,
platform komisyonu ayrı hesaplanır.
**Permissions**: `payment.customer.view`.
**Entitlement**: M2 edition, işlem başına komisyon modeli finansal detayı
henüz tanımlanmadı — mevcut gap kaydı `docs/16` §U2 OPT-COMM-01.
**ECA hooks**: Ödeme başarılı → sipariş tamamlama tetiklenir.
**AI-off/on**: AI'dan bağımsız.
**UX**: QR'dan hesap görüntüleme → ödeme yöntemi seçimi → onay.
**States**: `pending → succeeded/failed → refunded`.
**Retention**: Mali kayıt niteliğinde uzun süre saklanır.
**Security**: Kart bilgisi burada da **saklanmaz** (aynı Iyzico/ödeme
sağlayıcı disiplini, `docs/09` §5); PCI kapsamı genişletilmez.
**A11y/i18n**: Ödeme akışı WCAG 2.2 AA.
**Phase delivery**: Growth Stage.
**Acceptance**: Platform aboneliği ödemesi ile müşteri ödemesinin muhasebe
olarak karışmadığının testi (ayrı ledger girişleri).
**Rollback**: Disable edilirse yalnız masada nakit/mevcut ödeme yöntemi
kullanılır.
**Open questions**: Komisyon modeli tanımlanmadı — bkz. `docs/16` §U2 OPT-COMM-01.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Ödeme anomali bilgilendirmesi (karar değil)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: İşlem meta verisi (kart bilgisi asla değil)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Ödeme kararı/finalitesi AI'ya kapalıdır
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
