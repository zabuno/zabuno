# QR-print-scan

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Yeni bir QR tema/boyut şablonu eklendiğinde veya mevcut şablonun scannability
kalite kapısından geçtiği doğrulanması gerektiğinde (`docs/08` §4).

## Inputs
QR layout JSON, renk/logo parametreleri.

## Authority
Salt-okunur doğrulama — üretilen QR'ı otomatik decode ederek test eder.

## Permitted tools/actions
Server-side QR üretim + decode döngüsü, kontrast oranı hesaplama, quiet-zone
ölçümü.

## Forbidden actions
Düşük kontrastlı/logo-alanı-aşırı bir şablonu "görsel olarak güzel" diye
onaylama — yalnız ölçülebilir kalite kapısı geçerlidir.

## Deterministic outputs / schema
```
{ template_id, contrast_ratio, quiet_zone_ok: boolean, decode_success: boolean, error_correction_level }
```

## Evidence
Üretilen QR görseli + decode sonucu.

## Human approval
Yeni bir tema şablonu yayına alınmadan önce Design onayı gerekir (kalite
kapısı otomatik testi geçse bile).

## Failure / rollback
`decode_success: false` → şablon reddedilir, yayına alınmaz.

## Eval cases
- Maksimum logo alanı aşan bir tasarım → decode başarısız beklenir (test bunu
  doğrular, kabul edilmez).
- Minimum kontrast altı renk kombinasyonu → reddedilir.

## Phase
QR Print Export implementasyonu başladığında; her yeni şablon eklenişinde.
