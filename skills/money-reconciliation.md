# money-reconciliation

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Periyodik (günlük) ledger↔Iyzico uzlaştırma veya property-based money testi
gerektiğinde (`docs/09` §1, §5).

## Inputs
Ledger kayıtları + Iyzico işlem kayıtları (belirli tarih aralığı).

## Authority
Salt-okunur karşılaştırma — hiçbir ledger kaydını değiştirmez, yalnız
uyuşmazlık raporlar.

## Permitted tools/actions
İki kayıt setini eşleştirme, tutar/currency/minor-unit karşılaştırma.

## Forbidden actions
Uyuşmazlığı otomatik "düzeltme" (bu her zaman insan/Finance Operator kararı).

## Deterministic outputs / schema
```
{ period, ledger_total, provider_total, discrepancies: [{ tx_id, ledger_amount, provider_amount }] }
```

## Evidence
Uyuşmazlık listesi + ilgili işlem ID'leri.

## Human approval
Uyuşmazlık bulunursa Finance Operator'a escalate edilir, otomatik düzeltme
yapılmaz.

## Failure / rollback
N/A (salt-okunur, "başarısızlık" durumu yalnız uyuşmazlık raporudur).

## Eval cases
- Property-based test: rastgele tutar/currency kombinasyonlarında yuvarlama
  her zaman deterministik.
- Kısmi refund senaryosunda ledger'ın doğru iki-taraflı kayıt tuttuğu.

## Phase
Iyzico Payment canlıya alındığında (Stage 3 GTM).
