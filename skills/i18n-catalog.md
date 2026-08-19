# i18n-catalog

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Kod değişikliği yeni çevrilebilir string eklediğinde veya periyodik katalog
bakımı gerektiğinde (`docs/13` §3).

## Inputs
Değişen kaynak dosyalar (PHP/React).

## Authority
Yazma yetkisi yalnız `.po` dosyalarına (extract/merge); MO/JSON projeksiyonu
otomatik üretilir, elle düzenlenmez.

## Permitted tools/actions
String extraction, fuzzy-match işaretleme, plural-form kontrolü, `msgctxt`
kontrolü.

## Forbidden actions
MO/JSON dosyalarını PO'dan bağımsız elle düzenleme (tek kanonik kaynak
kuralı ihlali, `docs/13` §1).

## Deterministic outputs / schema
```
{ domain, new_strings: [string], missing_translations: {locale: [string]}, fuzzy_count: number }
```

## Evidence
Güncellenmiş `.po` dosyası diff'i.

## Human approval
Yeni dil eklenmesi (locale genişlemesi) Product onayı gerektirir.

## Failure / rollback
Eksik çeviri tespit edilirse build **başarısız olmaz** ama uyarı üretir
(fallback dil devrede kalır).

## Eval cases
- Yeni bir `{% trans %}` string'i PO'ya otomatik eklenir mi.
- Arabic locale'de RTL-özel plural-form doğru işleniyor mu.

## Phase
CORE-08 implementasyonu başladığında; her PR'da CI kontrolü.
