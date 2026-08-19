# tenant-isolation

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Yeni bir ORM sorgusu/endpoint eklendiğinde veya CI'da düzenli (nightly) IDOR
regresyon taraması gerektiğinde (`docs/16` AUTH-02).

## Inputs
Değişen query/controller dosyaları veya tam eval seti (nightly mod).

## Authority
Salt-okunur test yürütme — üretim verisine yazmaz, izole test tenant'ları
kullanır.

## Permitted tools/actions
Test tenant'ları oluşturma (yalnız test DB'de), cross-tenant erişim denemesi,
sonucu raporlama.

## Forbidden actions
Üretim tenant verisiyle test yapma; başarısız testi "flaky" diye görmezden
gelme.

## Deterministic outputs / schema
```
{ endpoint, method, cross_tenant_access_attempted: true, blocked: boolean, evidence }
```

## Evidence
Denemenin HTTP isteği/yanıtı (redakte edilmiş).

## Human approval
Gerekmez (otomatik test), ama başarısız sonuç Security rolüne escalate edilir.

## Failure / rollback
`blocked: false` bulunursa **kritik** öncelikli bug — merge bloklanır.

## Eval cases
- Workspace A kullanıcısının Workspace B'nin menü ID'siyle doğrudan erişim
  denemesi → `blocked: true` beklenir.
- URL'de workspace_id manipülasyonu → `blocked: true` beklenir.

## Phase
MVP Exit Gate'ten itibaren her PR + nightly regresyon.
