# module-spec

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Yeni bir modül önerildiğinde veya mevcut bir `modules/*.md` dosyası
güncellenmesi gerektiğinde.

## Inputs
Modül adı, amaç cümlesi, sınıf (Core/Required/Optional).

## Authority
`templates/MODULE-SPEC.md`'yi uygulayan yazma yetkisi — yalnız `modules/`
dizininde.

## Permitted tools/actions
`templates/MODULE-SPEC.md` okuma, `modules/<key>.md` yazma/düzenleme,
`docs/04` ve `docs/26`'ya çapraz referans ekleme.

## Forbidden actions
Şablonun zorunlu alanlarından herhangi birini atlama; "Core"u gerekçesiz
büyütme (`docs/04` §5 üç koşulu sağlanmadan CORE-17+ önerme).

## Deterministic outputs / schema
Tamamlanmış `modules/<key>.md` dosyası, şablonun tüm alanlarını dolu olarak
içerir; boş alan varsa `docs/16`'ya otomatik bir gap kaydı önerisi eklenir.

## Evidence
Yeni/güncellenen dosyanın diff'i.

## Human approval
Yeni bir CORE modülü önerisi Architecture onayı gerektirir.

## Failure / rollback
Şablon alanı eksikse dosya "taslak" olarak işaretlenir, `docs/26`'ya
eklenmez.

## Eval cases
- Boş "Open questions" alanı → uyarı değil, kabul edilebilir (gerçekten açık
  soru yoksa).
- Boş "Acceptance" alanı → reddedilir (her modülün kabul kriteri olmalı).

## Phase
Doküman üretimi sırasında (bu paket) ve implementasyon sırasında yeni modül
eklenirken.
