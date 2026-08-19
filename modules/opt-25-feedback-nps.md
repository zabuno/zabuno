# OPT-25 — Feedback / NPS

**PLANNING ONLY. Şu an çalıştırılamaz.**

**Amaç**: Müşteri geri bildirimi ve NPS (Net Promoter Score) toplamak.
**Bounded context**: Public Menu Delivery'nin sonuna eklenen bir anket katmanı;
sonuçlar Mini CRM ile ilişkilendirilebilir (opsiyonel).
**Owner**: Product + PMF Stage sahibi. **Sınıf**: Optional (M2, ama PMF Stage
ile kavramsal olarak ilişkili).
**Bağımlılıklar**: Publication (menü sonrası anket tetikleme), Mini CRM
(opsiyonel).
**Public contracts/events**: `FeedbackSubmitted`, `NPSScoreRecorded`.
**Tenant isolation**: Aynı.
**Permissions**: `feedback.view`.
**Entitlement**: M2 edition.
**ECA hooks**: Düşük NPS skoru → uyarı bildirimi.
**AI-off/on**: AI geri bildirim özetleyebilir (sentiment analizi); ham veri
değişmez.
**UX**: Menü sonunda tek tık puanlama (1-5 yıldız veya NPS 0-10).
**States**: Yok (immutable submission).
**Retention**: Geri bildirim verisi retention politikasına tabi (PII içermeme
tercih edilir — anonim toplama varsayılan).
**Observability**: NPS trend grafiği.
**Security**: Spam/bot submission önleme (rate limiting).
**A11y/i18n**: Anket formu WCAG 2.2 AA, çok dilli.
**Phase delivery**: PMF Stage (`docs/21`, retention/kullanım ölçümünün bir
parçası olarak).
**Acceptance**: Aynı cihazdan kısa sürede tekrar submission'ın engellendiğinin
(spam önleme) testi.
**Rollback**: Disable edilirse anket görünmez, menü etkilenmez.
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Serbest metin geri bildirim duygu/tema özetleme
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Geri bildirim metni (PII olabilir, redaction'a tabi)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Geri bildirime otomatik yanıt gönderme AI'ya devredilmez
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
