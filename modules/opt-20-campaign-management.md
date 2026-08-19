# OPT-20 — Campaign Management

**PLANNING ONLY. Şu an çalıştırılamaz.**

**Amaç**: İndirim/promosyon kampanyalarını (örn. "hafta sonu %10 indirim")
yönetmek.
**Bounded context**: CORE-12 Money ile fiyat hesaplamasını, QR Destination'ın
"campaign" destination tipini kullanır (`docs/08` §7).
**Owner**: Product + Finance Operator. **Sınıf**: Optional (M2).
**Bağımlılıklar**: CORE-12, QR Destination, Menu Catalog.
**Public contracts/events**: `CampaignActivated`, `CampaignExpired`.
**Tenant isolation**: Aynı.
**Permissions**: `campaign.manage`.
**Entitlement**: M2 edition.
**ECA hooks**: Kampanya bitiş tarihinde otomatik deaktivasyon.
**AI-off/on**: AI kampanya metni önerebilir; fiyat/indirim hesaplaması
deterministik (CORE-12).
**UX**: Kampanya oluşturma sihirbazı (tarih aralığı, indirim tipi, hedef
ürün/kategori).
**States**: `draft → scheduled → active → expired → archived`.
**Retention**: Kampanya geçmişi saklanır (mali raporlama için).
**Observability**: Kampanya bazlı satış/scan etkisi.
**Security**: İndirim hesaplamasının property-based test edilmesi (CORE-12
disiplini).
**A11y/i18n**: Standart.
**Phase delivery**: Growth Stage.
**Acceptance**: Kampanya bitiminde fiyatın otomatik normale döndüğünün testi.
**Rollback**: Disable edilirse aktif kampanyalar iptal edilir, orijinal
fiyatlar korunur.
**Open questions**: Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Kampanya metni/görsel brief taslağı önerisi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Pazarlama içeriği
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Kampanya yayınlama kararı insan eylemidir
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
