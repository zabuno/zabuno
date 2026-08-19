# ECA-cycle

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Yeni bir ECA kuralı tanımlandığında veya recursion/cycle-guard testi
gerektiğinde (`docs/10` §3).

## Inputs
Kural tanımı (event, condition AST, action).

## Authority
Salt-okunur simülasyon (dry-run) — kuralı gerçek veri üzerinde çalıştırmaz.

## Permitted tools/actions
Event→action zincirini simüle etme, kendi kendini tetikleme (cycle) tespiti,
idempotency kontrolü.

## Forbidden actions
`eval` veya serbest kod çalıştırma içeren bir condition/action'ı onaylama.

## Deterministic outputs / schema
```
{ rule_id, cycle_detected: boolean, max_depth_reached: boolean, idempotent: boolean, dry_run_result }
```

## Evidence
Simülasyon zinciri (hangi event hangi action'ı, o action hangi event'i
tetikledi).

## Human approval
`cycle_detected: true` → kural reddedilir, insan düzeltmeden yayına alınamaz.

## Failure / rollback
Cycle tespit edilirse kural `draft` durumunda kalır, `active`e geçemez.

## Eval cases
- A kuralı B event'ini tetikler, B kuralı A event'ini tetikler → cycle.
- Aynı event iki kez gönderilirse action'ın yalnız bir kez uygulandığı
  (idempotency).

## Phase
CORE-11 implementasyonu başladığında (Stage 2 Post-MVP).
