# auth-policy

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Yeni bir permission/policy eklendiğinde veya auth policy matrix testi
gerektiğinde (`docs/27` §5).

## Inputs
Rol × kaynak × eylem matrisi (`docs/02` §1, §2'den türetilir).

## Authority
Salt-okunur test yürütme.

## Permitted tools/actions
Her rol için test kullanıcısı oluşturma (test DB), her kaynak/eylem
kombinasyonunu deneme.

## Forbidden actions
Matrisi eksik test etme (yalnız "mutlu yol" rollerini test edip kenar
rolleri atlamak yasak).

## Deterministic outputs / schema
```
{ role, resource, action, expected: allow|deny, actual: allow|deny, match: boolean }
```

## Evidence
Matris tablosu tam çıktısı.

## Human approval
Gerekmez (otomatik), ama `match: false` bulunursa Security escalation.

## Failure / rollback
Herhangi bir `match: false` → merge bloklanır.

## Eval cases
- Editor'ün `billing.manage` eylemini deneyip reddedilmesi (`docs/02` §1).
- Impersonation sırasında billing/silme işleminin engellendiği (`docs/05` §4).

## Phase
MVP Exit Gate'ten itibaren her PR.
