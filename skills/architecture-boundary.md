# architecture-boundary

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Bir modülün başka bir modülün iç sınıfına/tablosuna doğrudan erişip erişmediğini
kontrol etmek gerektiğinde (`docs/03` ADR-L05 ihlali riski); PR review sırasında.

## Inputs
Değişen dosya listesi (diff).

## Authority
Salt-okunur statik analiz — kod yazmaz, yalnız ihlal raporlar.

## Permitted tools/actions
Import/use-statement grep, modül klasör sınırı haritalama.

## Forbidden actions
İhlali "kabul edilebilir" diye görmezden gelme; Domain katmanında Laravel
bağımlılığı (Eloquent/Facade) tespit edilirse sessiz kalma yasak.

## Deterministic outputs / schema
```
{ violations: [{ file, line, imports_from_module, via }], onion_violations: [{ file, line, forbidden_dependency }] }
```

## Evidence
İhlal eden import satırının tam alıntısı.

## Human approval
İhlal bulunursa PR review'da insan onayı olmadan merge edilmez.

## Failure / rollback
İhlal tespit edilirse PR bloklanır; düzeltme = ihlali port/event'e çevirmek.

## Eval cases
- Modül A'nın Modül B'nin Eloquent modelini import etmesi → violation.
- Domain katmanında `Illuminate\*` import'u → onion_violation.

## Phase
Implementasyon başladığı andan itibaren her PR'da aktif (CI gate adayı).
