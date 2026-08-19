# QR Destination

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Stabil, tahmin edilemez QR token'ını çözümleyip doğru destination'a
yönlendirmek.

## Bounded context
Resolve mantığı + destination veri modeli. QR'ın görsel/basılı üretimi QR
Print Export modülünün sorumluluğunda.

## Owner
Engineering.

## Sınıf
Required product.

## Bağımlılıklar
Publication (destination çoğunlukla bir MenuPublication'a işaret eder), CORE-02.

## Public contracts / events
`DestinationPort::resolve(token)`; `QRResolved`, `DestinationChanged`,
`QRRevoked` event'leri.

## Tenant isolation
QR/destination tenant/location-scoped.

## Permissions
`qr.create/update/disable`.

## Entitlement / quota
Maksimum QR sayısı CORE-04 üzerinden.

## ECA hooks
`QRResolved` → analytics event'ine (Confirmed Menu Open ayrımıyla, `docs/12`
§2) dönüştürülebilir.

## AI-off / AI-on davranışı
AI'dan bağımsız.

## UX one-click journey
QR listesinde tek tık destination değiştirme (fiziksel QR aynı kalır).

## States
`draft → active → disabled → archived → deleted` (`docs/10` §2).

## Data retention / export
Revoke edilen destination'ın geri yüklenmesi (restore) veya aynı fiziksel
QR'ın yeni bir destination'a yeniden bağlanması (rotate) semantiği açık
madde — `docs/16` QR-03.

## Observability
QR resolve hızı (p95), disabled/archived QR sayısı.

## Security / privacy
Token tahmin edilemez olmalı (kriptografik rastgelelik, sequential ID
**yasak**, `docs/08` §2).

## Accessibility / i18n
Yok (bu modül kullanıcı arayüzü değil, resolver altyapısı).

## Phase delivery
Stage 1 MVP — Published menu + Menu category destination tipleri; Stage 2+'de
URL/social/campaign/PDF/contact/Wi-Fi/reservation destination tipleri.

## Acceptance
Token tahmin edilemezlik testi (yeterli entropi); revoke sonrası eski QR'ın
artık çalışmadığının testi.

## Rollback
Disable edilemez (required product, MVP kritik yolu).

## Open questions
301 mi 302 mi redirect kullanılacak (`docs/16` QR-01); restore/rotate
semantiği (`docs/16` QR-03).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Destination sağlık kontrolü açıklaması (örn. "bu QR 404 alıyor, muhtemel neden X")
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: QR/destination meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Resolver kararı (hangi destination'a yönlendirileceği) tamamen deterministiktir
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
