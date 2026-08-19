# ai-no-credit-degradation

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
AI invoke isteği şu durumlardan birine düşer: globally disabled (kill
switch), tenant disabled, no credential, no provider credit, internal credit
zero, budget cap, 429/quota, outage, residency/policy denial, safety block,
invalid schema (`docs/14` §1, `modules/ai-platform.md` §AI-off deterministik yol).

## Inputs
`{ module, feature, tenant, denial_reason, in_flight_draft (varsa) }`.

## Authority
Salt-okunur/deterministik davranış uygulayıcısı — yeni bir karar
**üretmez**, yalnız önceden tanımlı degraded-UX kuralını uygular.

## Permitted tools/actions
Kullanıcı girdisi/taslağının korunması, deterministik templates/rules/manual
UX'e geçiş, açık degraded mesajı gösterimi, tek-tık manuel devam/retry-later
seçeneği sunumu.

## Forbidden actions
Veri kaybı; gizli ücret veya otomatik top-up; kullanıcıyı bilgilendirmeden
sessizce farklı (daha düşük kaliteli veya daha pahalı) bir provider'a
yönlendirme; "AI kapalı" durumunu bir entitlement reddi gibi sunma (AI
kullanılabilirliği entitlement ön-koşulu değildir).

## Deterministic outputs / schema
```
{ degraded: true, reason, preserved_draft: object|null,
  manual_path_available: true, retry_option: "manual" | "retry_later" }
```

## Evidence
Her degradation olayı CORE-07'ye `AIDegradationTriggered` event'i olarak
yazılır (reason + module + tenant).

## Human approval
N/A — bu skill insan onayı **istemez**, tam tersine insanı normal/manuel
akışa **döndürür**.

## Failure / rollback
Bu skill'in kendisi başarısız olursa (örn. degraded-UX render edilemezse),
en muhafazakâr geri düşüş modülün **hiç AI göstermemesi**dir — asla kısmi/
tutarsız bir AI durumu kullanıcıya sızdırılmaz.

## Eval cases
- Kill switch aktifken bir formun taslağının kaybolmadığının testi.
- Sıfır iç krediyle bir isteğin gizli ücrete yol açmadığının testi.
- 429 sonrası kullanıcının "şimdi manuel devam et" seçeneğini gördüğünün testi.

## Phase
Stage 2 Post-MVP'den itibaren, `ai-platform` ile birlikte.
