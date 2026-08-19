# iyzico-contract

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Iyzico webhook alındığında veya webhook doğrulama mantığı değiştirildiğinde
test gerektiğinde (`docs/09` §5).

## Inputs
Webhook payload + imza header'ı.

## Authority
Salt-okunur doğrulama — webhook'u işlemez, yalnız imza/format doğrular.

## Permitted tools/actions
HMAC/V3 signature doğrulama, replay-window kontrolü, conversation ID
tekilliği kontrolü.

## Forbidden actions
İmzası geçersiz bir webhook'u "muhtemelen doğrudur" diye işleme alma.

## Deterministic outputs / schema
```
{ webhook_id, signature_valid: boolean, replay_detected: boolean, action: process|reject }
```

## Evidence
Payload hash'i + doğrulama sonucu.

## Human approval
Gerekmez (otomatik gate), ama tekrarlanan imza hatası Security'ye escalate
edilir (olası saldırı sinyali).

## Failure / rollback
`signature_valid: false` → webhook reddedilir, işlenmez, loglanır.

## Eval cases
- Geçerli imzalı webhook → işlenir.
- Aynı webhook iki kez gönderilirse (replay) → ikinci deneme reddedilir.
- Bozuk/eksik imza → reddedilir.

## Phase
Iyzico Payment sandbox entegrasyonundan itibaren (Stage 1 MVP sandbox testi).
