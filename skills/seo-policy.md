# SEO-policy

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Yeni bir public sayfa yayınlandığında veya pSEO ölçeklendirme talebi
geldiğinde (`docs/12` §7, §8).

## Inputs
Sayfa içeriği + meta veri.

## Authority
Salt-okunur kalite kapısı — sayfayı yayına almaz/almaz kararını verir, içerik
üretmez.

## Permitted tools/actions
Thin-content skorlama, structured data (JSON-LD) şema doğrulama, canonical/
hreflang kontrolü.

## Forbidden actions
Gray/black/negative/parasite SEO tekniklerini **önerme veya uygulama** —
bunlar bu skill'in kapsamı dışında, risk/tehdit sınıfındadır (`docs/12` §8).
`llms.txt`'i sıralama girdisi olarak değerlendirme yasak.

## Deterministic outputs / schema
```
{ url, thin_content_score, structured_data_valid: boolean, canonical_ok: boolean, publish_allowed: boolean }
```

## Evidence
Skor detayları + hangi kriterin başarısız olduğu.

## Human approval
`publish_allowed: false` çıkan bir pSEO sayfası insan review'ı olmadan
yayınlanamaz.

## Failure / rollback
Kalite kapısından geçemeyen sayfa yayına alınmaz, taslakta kalır.

## Eval cases
- Thin-content eşiği altı bir pSEO sayfası → `publish_allowed: false`.
- Geçersiz JSON-LD şeması → reddedilir.

## Phase
Stage 3 GTM'den itibaren (SEO/Search & Discovery modülü canlı olduğunda).
