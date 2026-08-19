# OPT-16 — Reservation

**PLANNING ONLY. Şu an çalıştırılamaz.**

**Amaç**: Müşterinin restoran için masa rezervasyonu yapmasını sağlamak.
**Bounded context**: QR Destination'daki "reservation page" destination tipini
gerçek bir modüle dönüştürür (`docs/08` §7).
**Owner**: Product + Engineering. **Sınıf**: Optional (M2).
**Bağımlılıklar**: CORE-02 (Location/Table kapasitesi), CORE-14 (bildirim/
onay).
**Public contracts/events**: `ReservationRequested`, `ReservationConfirmed`,
`ReservationCanceled`.
**Tenant isolation**: Aynı.
**Permissions**: `reservation.view`, `reservation.manage`.
**Entitlement**: M2 edition.
**ECA hooks**: Rezervasyon onaylandığında hatırlatma bildirimi.
**AI-off/on**: AI'dan bağımsız.
**UX**: Tarih/saat/kişi sayısı seçimi → onay bekleme → onaylandı bildirimi.
**States**: `requested → confirmed → seated → completed/canceled/no_show`.
**Retention**: Rezervasyon geçmişi saklanır.
**Observability**: No-show oranı, doluluk oranı.
**Security**: Spam rezervasyon önleme (rate limiting).
**A11y/i18n**: Rezervasyon formu WCAG 2.2 AA, çok dilli.
**Phase delivery**: Growth Stage.
**Acceptance**: Kapasite aşımı durumunda yeni rezervasyonun reddedildiğinin
testi.
**Rollback**: Disable edilirse yalnız iletişim bilgisi üzerinden manuel
rezervasyon kalır.
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: No-show riski tahmini ve masa atama önerisi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Rezervasyon meta verisi (PII sınırlı)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Rezervasyon onay/red kararı insan/deterministik kuraldadır
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
