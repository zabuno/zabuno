# analytics-event

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Yeni bir event türü eklendiğinde veya dataLayer sözleşmesi değiştirildiğinde
(`docs/12` §1).

## Inputs
Event şeması taslağı.

## Authority
Yazma yetkisi yalnız versiyonlanmış event şema kaydına; canlı event ledger'a
yazmaz.

## Permitted tools/actions
Şema doğrulama, PII alan taraması (event şemasında yanlışlıkla PII alanı
varsa reddetme), consent-gate kontrolü.

## Forbidden actions
PII içeren bir event şemasını onaylama; consent kontrolünden geçmeyen bir
tag'in tetiklenmesine izin verme.

## Deterministic outputs / schema
```
{ event_name, version, pii_detected: boolean, consent_gated: boolean, approved: boolean }
```

## Evidence
Şema diff'i + PII tarama sonucu.

## Human approval
Yeni event türü Engineering + Privacy (varsa) onayı gerektirir.

## Failure / rollback
`pii_detected: true` → şema reddedilir, PII alanı çıkarılmadan onaylanmaz.

## Eval cases
- Bir event şemasına yanlışlıkla `email` alanı eklenmesi → reddedilir.
- QR Resolve ile Confirmed Menu Open event'lerinin ayrı şemalarının doğru
  tanımlandığı (`docs/12` §2).

## Phase
Analytics/Consent/Tagging implementasyonu başladığında.
