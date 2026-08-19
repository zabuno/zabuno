# Menu Catalog

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Menü, kategori, ürün ve ürün gösterim bilgilerini yönetmek.

## Bounded context
Editable (draft) veri modeli. Yayınlanan (published) veri Publication
modülünün sorumluluğundadır — bu ikisi **kasıtlı olarak ayrıdır**.

## Owner
Product + Engineering.

## Sınıf
Required product.

## Bağımlılıklar
CORE-02, CORE-13 (medya), CORE-09 (alerjen taxonomy'si).

## Public contracts / events
`ProductCreated/Updated/Hidden`, `MenuItemPriceChanged`,
`CategoryReordered` event'leri.

## Tenant isolation
Tüm varlıklar tenant/location-scoped.

## Permissions
`menu.view/create/update/publish`, `product.create/update/hide/delete`
(`docs/02` §5).

## Entitlement / quota
Maksimum menü/kategori/ürün sayısı CORE-04 üzerinden.

## ECA hooks
`MenuItemPriceChanged` → audit + opsiyonel bildirim; stok durumu değişimi ECA
ile otomatikleştirilebilir (Post-MVP).

## AI-off / AI-on davranışı
AI ürün açıklaması üretebilir (OPT-23) ama ürün/fiyat/görünürlük verisi her
zaman insan onayıyla kaydedilir.

## UX one-click journey
Hızlı ürün ekle, fiyat değiştir tek ekranda (`docs/06` Dashboard hızlı
eylemler).

## States
Product: `draft → visible → hidden → out_of_stock → archived` (`docs/10` §2,
`hidden` ≠ `out_of_stock` ayrımı kritik).

## Data retention / export
Ürün silinirse geçmiş publication snapshot'ı bozulmaz (immutable snapshot,
Publication modülü ile koordineli).

## Observability
Ürün/kategori sayısı trendi, en sık değiştirilen ürünler.

## Security / privacy
Yok (özel risk yok, standart tenant izolasyonu).

## Accessibility / i18n
Ürün/kategori adları çok dilli (OPT-04 ile genişler); form WCAG 2.2 AA.

## Phase delivery
Stage 1 MVP — tam kapsam (tek menü); OPT-01/02/03 Stage 2+.

## Acceptance
Product≠MenuItem ayrımının doğru çalıştığının testi (aynı ürün iki menüde
farklı fiyatla); alerjen listesinin public menüde göründüğünün testi.

## Rollback
Disable edilemez (required product, MVP kritik yolu).

## Open questions
İki çalışanın aynı ürünü aynı anda düzenlemesi senaryosu — en az uyarı ile
başlanacak (`docs/16` OPS-03).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Ürün/kategori veri kalitesi önerisi (eksik alan, tutarsız fiyat birimi uyarısı)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Menü/ürün içeriği (işletme verisi)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Kayıt yazma/silme kararı insan onayı gerektirir; kalite önerisi otomatik uygulanmaz
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
