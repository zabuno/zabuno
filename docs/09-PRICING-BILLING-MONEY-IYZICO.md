# 09 — Pricing, Billing, Money & Iyzico

**PLANNING ONLY.**

## 1. Para modeli — asla float değil

**brick/money** + **brick/math** — **capability-verified, adoption koşullu**.
Güncel GitHub release `0.14.1` (2026-07-30 yayınlandı, 2026-08-19'da erişim
doğrulandı, `docs/28`); ana composer gereksinimi PHP `^8.2`. Exact
arithmetic/immutability/explicit-rounding kabiliyeti resmi kaynakla
doğrulanmıştır, ancak sürümün kendisi hâlâ `0.x` (henüz `1.0` değil) — bu,
production benimsemesinin **koşullu** kalması gerektiği anlamına gelir: exact
sürüm pinlenir, property-based testler (`docs/27` §Property-Based Money
Tests) tam kapsamlı çalıştırılır ve bir adapter/port katmanı arkasında
kullanılır ki majör bir breaking-change durumunda **rollback** mümkün olsun.
Kesin sürüm seçimi hedef PHP/shared-host baseline'ının kilitlenmesine
bağlıdır (`docs/16` DEP-01).
Fiyatlar `float` olarak **saklanmaz**:

```
420,00 TL  →  amount_minor = 42000, currency = TRY
```

Currency, minor unit, yuvarlama, vergi, indirim ve proration (kısmi dönem
hesaplama) kararları **deterministik politika** ile tanımlanır — her hesaplama
aynı girdiyle her zaman aynı çıktıyı üretir (property-based test edilebilir,
bkz. `docs/27` §Property-Based Money Tests).

## 2. Ledger — ayrı, immutable, double-entry

Fatura/abonelik durumundan **ayrı** bir immutable double-entry ledger tutulur
(reconciliation için). **eloquent-ifrs** yalnız **R&D candidate**'tır — bağımsız
doğrulama (spike + muhasebe uzmanı review) tamamlanmadan production kararı
verilmez (deneysel sınıf).

## 3. Plan/entitlement versiyonlama

- Plan'lar **versiyonludur**; aktif bir abonelik her zaman belirli bir plan
  **versiyonuna pinlidir** (plan güncellenirse mevcut aboneler otomatik
  etkilenmez — açık bir migrasyon kararı gerekir).
- Frontpage'deki pricing gösterimi **published projection**'dır (superadmin
  draft → preview → **four-eyes publish** (iki kişi onayı) → rollback → audit
  döngüsünden geçer; segregation-of-duties burada da geçerlidir).

## 4. Entitlement/limit envanteri

Kullanıcının tüm sınırları:

**Zorunlu (MVP)**: restoran sayısı, kullanıcı sayısı, menü sayısı, QR sayısı,
storage kotası, aylık tarama limiti, kullanılabilir modüller, branding seviyesi,
analytics saklama süresi.

**Genişletilmiş (M1+)**: branch/location sayısı, custom domain, dil sayısı,
tema/media recipe erişimi, API/webhook erişimi, AI credit/model/tool erişimi,
support/SLA seviyesi, export/retention hakları.

Tüm limitler **backend'de zorunlu kılınır** — yalnız menü/UI görünürlüğüyle
uygulanmaz (`docs/03` ADR disiplini + `docs/04` CORE-04 Entitlements modülü).

## 5. Iyzico entegrasyonu

- Resmi **iyzipay-php** adaptörü kullanılır (kanıtlanmış).
- Akışa göre: Checkout Form, direct, 3DS, subscription — hepsi gerektiğinde
  kullanılabilir aday akışlardır, hepsi ilk günden zorunlu değildir.
- **Kart bilgisi asla saklanmaz** (PCI kapsamını genişletmemek için).
- Idempotency/conversation ID'leri her istekte zorunlu (çift ödeme önleme).
- Server-side tutar doğrulaması (client'tan gelen tutara güvenilmez).
- Webhook **V3 HMAC/signature** doğrulaması + replay protection (aynı webhook
  event'inin iki kez işlenmemesi).
- Reconciliation (ledger ile Iyzico kayıtlarının periyodik uzlaştırılması),
  refund/cancel/chargeback akışları, sandbox → live geçiş kapıları, secret'lerin
  güvenli saklanması + tüm işlemlerin audit'e yazılması.

## 6. MVP'de manuel ödeme + Iyzico sandbox dikey dilimi

Stage 1 MVP'de **iki** ödeme yolu birlikte bulunur — biri diğerinin yedeği
değil, ikisi de MVP kapsamıdır:

- **Manuel ödeme**: Pilot müşteriler için — manuel ödeme kaydı, süperadmin
  plan ataması, bitiş tarihi, ödeme notu, belge referansı.
- **Iyzico sandbox dikey dilimi** (canlı/production para akışı **değil**):
  adaptör (iyzipay-php), sandbox checkout + gerektiğinde 3DS, server-side
  tutar doğrulaması, idempotency/conversation ID, imzalı response/webhook
  doğrulaması + replay protection, deterministik success/failure durumları.
  Bu dilim MVP'de **çalışır durumda** teslim edilir; yalnız gerçek para
  hareket etmez (sandbox).

Daha derin akışlar (recurring payment, invoice, refund, chargeback,
reconciliation) MVP'nin dışındadır ve M1/Post-MVP'de eklenir — bkz. `docs/26`
modül×stage matrisi ve `modules/iyzico-payment.md`.

Stage 3 GTM'de **live switch**: sandbox'tan canlıya geçiş yalnız
operasyonel/hukuki/güvenlik/reconciliation/rollback kapıları geçildikten sonra
açılır (`docs/18` Stage 1 kapsamı, `docs/26` §1).

## 7. Abonelik sonrası QR politikası (iş kararı, geliştirme öncesi netleştirilmeli)

```
Active     → panel ve public QR çalışır
Past Due   → panel uyarı gösterir, public QR çalışır
Grace      → düzenleme kısıtlanabilir, public QR çalışır
Suspended  → panel billing ekranına kilitlenir; public QR için configurable kural
Canceled   → veri retention süresi başlar
```

Public QR'ın aniden kapanması restoran müşterisine platformun ödeme sorununu
doğrudan yansıtır — bu yüzden grace ve "son yayınlanan menüyü koru" politikası
**açıkça** tanımlanmadan geliştirme başlamaz. Kesin gün sayıları `docs/16`'da
owner kararı bekleyen açık madde olarak durur.

## 8. Kanonik sahiplik

Para modeli, ledger, entitlement envanteri ve Iyzico entegrasyon sözleşmesi
burada kanoniktir. Plan/edition × modül matrisi `docs/26`'da, resmi Iyzico kaynak
kaydı `docs/28`'de yaşar.
