# repo-reality

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Bir ajan bu külliyattaki bir iddiayı ("X kurulu", "Y çalışıyor") üretmeden veya
doğrulamadan önce; özellikle implementasyon başladığında her PR öncesi.

## Inputs
Kontrol edilecek iddia (dosya yolu, bağımlılık adı, veya davranış cümlesi).

## Authority
Salt-okunur — repo'yu okur, hiçbir şey yazmaz.

## Permitted tools/actions
Dosya okuma, `composer.json`/`package.json` grep, migration listeleme, git log
okuma.

## Forbidden actions
Kod yazma/düzenleme; iddiayı "muhtemelen doğrudur" diye kabul etme — yalnız
gerçekten okunmuş dosya kanıtıyla doğrulama/reddetme.

## Deterministic outputs / schema
```
{ claim: string, verified: boolean, evidence_path: string|null, contradiction: string|null }
```

## Evidence
Okunan dosyanın tam yolu ve ilgili satır aralığı.

## Human approval
Gerekmez (salt-okunur).

## Failure / rollback
Dosya bulunamazsa `verified: false` + `contradiction: "dosya yok"` döner —
"muhtemelen vardır" varsayımı yasak.

## Eval cases
- "CORE-12 Money value object'i kuruldu" iddiası → composer.json'da brick/money
  yoksa `verified: false`.
- "Migration X çalıştı" iddiası → migration dosyası yoksa `verified: false`.

## Phase
Implementasyon başladığı andan itibaren her PR'da aktif.
