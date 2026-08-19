# OPT-14 — Online Ordering

**PLANNING ONLY. Şu an çalıştırılamaz.**

**Amaç**: Müşterinin QR menüden doğrudan sipariş vermesini sağlamak.
**Bounded context**: Public Menu Delivery'nin üzerine kurulur, kendi sipariş
veri modelini (Order, OrderItem) ekler. Masa numarası kavramı (`docs/16` OPS-02
proxy sorusu) burada gerçek anlam kazanır.
**Owner**: Product + Engineering. **Sınıf**: Optional (M2).
**Bağımlılıklar**: Menu Catalog, QR Destination (masa-bazlı QR), Restaurant
Payment (OPT-15, opsiyonel ilişki).
**Public contracts/events**: `OrderPlaced`, `OrderAccepted`, `OrderCompleted`,
`OrderCanceled`.
**Tenant isolation**: Aynı.
**Permissions**: `order.view`, `order.manage`.
**Entitlement**: M2 edition.
**ECA hooks**: Yeni sipariş → mutfak bildirim kuralı.
**AI-off/on**: AI'dan bağımsız — sipariş kabul/red her zaman insan (restoran
personeli) kararıyla.
**UX**: Sepete ekle → onayla → sipariş takibi (durum göstergesi).
**States**: `placed → accepted → preparing → ready → completed/canceled`.
**Retention**: Sipariş geçmişi saklanır (mali kayıt niteliğinde).
**Observability**: Sipariş hacmi, ortalama hazırlama süresi.
**Security**: Guest sipariş akışında rate-limiting (spam sipariş önleme).
**A11y/i18n**: Sipariş akışı WCAG 2.2 AA, çok dilli.
**Phase delivery**: Growth Stage (`docs/22`).
**Acceptance**: Aynı masadan çakışan siparişlerin doğru sıralandığının testi.
**Rollback**: Disable edilirse menü salt-görüntüleme moduna döner.
**Open questions**: Masa numarasının bu modüle geçişte veri modeli genişlemesi
gerektiği — `docs/16` OPS-02'de zaten işaretli.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Sipariş anomali tespiti (örn. olağandışı miktar) bilgilendirmesi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Sipariş meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Sipariş kabul/red kararı deterministik iş kuralındadır
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
