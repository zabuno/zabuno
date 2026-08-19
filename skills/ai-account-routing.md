# ai-account-routing

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
`ai-platform` bir feature invoke etmek istediğinde, hangi provider hesabının
kullanılacağını çözme ihtiyacı (`modules/ai-provider-account-vault.md`
§Feature × provider/model × account × policy × tenant/residency routing).

## Inputs
`{ feature, tenant, policy, residency_requirement, session_id }` — vault'taki
aktif hesap listesi ve her hesabın güncel health/cost/latency/quota durumu.

## Authority
Salt-okunur karar üretir — hesabı seçer, hesabı **oluşturmaz/silmez/rotasyona
sokmaz** (bu insan eylemidir, `modules/ai-provider-account-vault.md`
§UX one-click journey).

## Permitted tools/actions
Priority/weighted/cost/latency/health skorlama, session affinity kontrolü,
idempotency anahtarı üretimi, circuit-breaker durumu okuma.

## Forbidden actions
Tüketici ChatGPT/Claude Pro/Max girişini bir hesap adayı olarak **kabul
etmek**; bir providerın rate/quota limitini aşmak için otomatik hesap
değiştirme (bu, kota/rate-limit evasion'dır — kesin yasak, kök yönetişim
talimatı madde 6); tenant BYOK hesabını başka bir tenant'ın adayları arasına
sızdırmak.

## Deterministic outputs / schema
```
{ selected_account_id, reason: "priority|weighted|cost|latency|health",
  fallback_chain: [account_id, ...], idempotency_key, session_affinity: boolean }
```

## Evidence
Her routing kararı `docs/07`/`docs/32` audit disipliniyle CORE-07'ye
`AccountRoutingResolved` event'i olarak yazılır.

## Human approval
Yeni bir hesabın routing havuzuna **eklenmesi** insan onayı gerektirir; bu
skill yalnız zaten onaylanmış hesaplar arasında seçim yapar.

## Failure / rollback
Seçilen hesap 429/quota/outage/residency-denial döndürürse, `fallback_chain`
içindeki bir sonraki **ayrı, sözleşmeye uygun** hesaba geçilir; hiçbir aday
kalmazsa `modules/ai-platform.md` §AI-off deterministik yoluna düşülür —
kullanıcı girdisi/taslağı korunur, gizli ücret/otomatik top-up yoktur.

## Eval cases
- Bir hesap 429 döndürdüğünde Retry-After/backoff'a uyulduğunun testi.
- Tenant BYOK hesabının başka tenant'ın adayları arasında **hiç** görünmediğinin
  testi.
- Tüm adaylar tükendiğinde AI-off deterministik yola düşüldüğünün testi.

## Phase
Stage 2 Post-MVP'den itibaren (`ai-provider-account-vault` canlı olduğunda).
