# accessibility-UX

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Yeni bir UI bileşeni/ekran eklendiğinde veya WCAG 2.2 AA regresyon testi
gerektiğinde (`docs/06` §8, `docs/15` §6).

## Inputs
Değişen component/sayfa listesi.

## Authority
Salt-okunur denetim — otomatik düzeltme yapmaz, ihlalleri raporlar.

## Permitted tools/actions
Kontrast oranı hesaplama, ARIA etiket kontrolü, klavye navigasyon testi, RTL
görsel regresyon karşılaştırması.

## Forbidden actions
AA-altı bir kontrastı "kabul edilebilir" diye görmezden gelme; kritik akış
(ödeme, hesap silme, export) için AAA hedefinin değerlendirilmediği bir raporu
eksiksiz sayma (`docs/15` §6).

## Deterministic outputs / schema
```
{ component, wcag_level_achieved: "A"|"AA"|"AAA"|"fail", violations: [{ rule, element }], rtl_regression: boolean }
```

## Evidence
Ekran görüntüsü + ihlal listesi.

## Human approval
`wcag_level_achieved: "fail"` → merge bloklanır; AAA hedefi kritik akışlarda
karşılanmazsa Design review gerekir (blok değil, gerekçeli istisna mümkün).

## Failure / rollback
Regresyon tespit edilirse önceki uyumlu versiyona geri dönülür.

## Eval cases
- Yeni bir buton renginin kontrast oranı 4.5:1 altındaysa → fail.
- RTL modda bir ikonun ters çevrilmesi gerekirken çevrilmemiş olması →
  rtl_regression: true.

## Phase
MVP Exit Gate'ten itibaren her PR (özellikle `docs/06` global panel shell
bileşenleri).
