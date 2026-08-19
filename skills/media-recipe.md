# media-recipe

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Yeni bir görsel slot/recipe tanımlandığında veya mevcut recipe'nin fingerprint
davranışı doğrulanması gerektiğinde (`docs/07` §2, §3).

## Inputs
Recipe tanımı (boyut, format, crop parametreleri) + test görseli.

## Authority
Salt-okunur doğrulama + test ortamında türev üretimi (üretim asset'lerine
yazmaz).

## Permitted tools/actions
Fingerprint hesaplama, aynı girdiyle iki kez çalıştırıp idempotence kontrolü,
WebP/AVIF çıktı doğrulama.

## Forbidden actions
"Görseli WebM'e çevir" gibi format-mantığı ihlali içeren bir recipe'yi sessizce
kabul etme — bu tip bir istek **reddedilir** (`docs/07` §4).

## Deterministic outputs / schema
```
{ recipe_id, fingerprint, idempotent: boolean, output_formats: [string], rejected_reason: string|null }
```

## Evidence
Üretilen türev dosyalarının checksum'ı.

## Human approval
Gerekmez (otomatik test).

## Failure / rollback
Idempotence testi başarısız olursa (aynı girdi farklı fingerprint üretirse) →
recipe reddedilir.

## Eval cases
- Aynı kaynak + aynı crop parametreleri iki kez işlenince aynı fingerprint.
- "video slot'una still image recipe" karışıklığı → reddedilir.

## Phase
CORE-13 implementasyonu başladığında.
