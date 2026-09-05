# OPT-06 — Advanced Analytics

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

**Amaç**: Cihaz/browser/OS/ülke/şehir/referrer/saat yoğunluğu kırılımlarını ve
export'u sağlamak.
**Bounded context**: Analytics/Consent/Tagging modülünün üzerine kurulur, temel
veri kaynağı aynıdır.
**Owner**: Engineering + (Growth/Marketing rolü). **Sınıf**: Optional (M1).
**Bağımlılıklar**: Analytics/Consent/Tagging.
**Public contracts/events**: `AdvancedReportGenerated`.
**Tenant isolation**: Aynı.
**Permissions**: `analytics.view.advanced`.
**Entitlement**: Plan'a bağlı (Growth+ edition).
**ECA hooks**: Yok.
**AI-off/on**: AI özet/insight üretebilir; ham veri değişmez.
**UX**: Kırılım filtreleri + tek tık export.
**States**: Yok.
**Retention**: Temel modülden daha uzun retention plana bağlı olabilir.
**Observability**: Rapor üretim süresi.
**Security**: Coğrafi konum tespiti için üçüncü taraf servis kullanılırsa
KVKK değerlendirmesi gerekir (`docs/16` §D).
**A11y/i18n**: Grafik/tablo çift sunum (erişilebilirlik).
**Phase delivery**: Stage 5 Growth (edition matrisiyle uyumlu, `docs/26` §2).
**Acceptance**: Export'un doğru filtrelenmiş veriyi ürettiğinin testi.
**Rollback**: Disable edilirse temel analytics'e döner.
**Open questions**: Şehir/ülke tespiti için üçüncü taraf servis kararı
verilmedi.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Trend/anomali içgörü anlatımı (kohort, retention)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Agregat analitik verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Rapor üretimi deterministiktir, AI yalnız yorumlar
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
