# Tasarım külliyatı — felsefe belgeleri

Zabuno'nun tasarım yaklaşımının **kaynak külliyatı**. Bu belgeler
2026-08-26'da owner kararıyla depoya taşındı; daha önce yalnız owner
makinesinde yaşıyorlar ve bir klonla gelmiyorlardı.

`docs/06`, `docs/35` ve `docs/03` bu külliyatın **sentezidir**. Burası
gerekçenin kendisidir: bir kararın neden o şekilde alındığını buradan
öğrenirsiniz.

> **Not:** Bu belgeler tarihsel kayıttır, canlı sözleşme değil. Bağlayıcı
> sözleşmeler `docs/06`, `docs/35` ve `docs/03`'tür. İkisi çeliştiğinde
> `docs/` kökündeki numaralı belge kazanır; bu dizin gerekçeyi açıklar,
> kuralı koymaz.

Dosya adları depo uyumlu hâle getirildi (boşluk/parantez/Türkçe karakter
kaldırıldı) ve mutlak yerel yollar `~` ile değiştirildi. İçerik başka hiçbir
yerinden değiştirilmedi — 28 dosyanın tamamı bayt bazında birebirdir.

## Çekirdek felsefe

| Dosya | Ne dondurur | Özgün ad |
|---|---|---|
| [`saas-panel-tasarim-sistemi.md`](saas-panel-tasarim-sistemi.md) | **Öncelik sırası**; Flat 2.0 + contextual cards formülü; form/table disiplinleri; 2026–2035 vizyonu | `SaaS Panel Tasarım Sistemi.md` |
| [`adaptive-semantic-grid.md`](adaptive-semantic-grid.md) | **ASG-320**: 320px-first, semantic-priority, constraint-driven düzen; container-first bileşen; logical koordinatlar | `Adaptive Semantic Grid.md` |
| [`olcu-birimleri.md`](olcu-birimleri.md) | **Ölçü birimi nihai kararı** — "bileşen yalnız semantic token bilir"; Primitive→Semantic→Component→Resolver→Adapter zinciri | `olcu-birimleri.md` |
| [`tasarim-paradigmalari.md`](tasarim-paradigmalari.md) | Data-dense Flat 2.0; form, tablo, kart, motion ve sınırlı glass kullanımı | `Tasarım Paradigmaları.md` |
| [`aep-grid-ve-layout-sistemi.md`](aep-grid-ve-layout-sistemi.md) | AI tarafından okunabilir layout state'leri; grid ve responsive/adaptive sözleşmeleri | `AEP Design System — Grid ve Layout Sistemi (320px-First, AI-First Enterprise SaaS).md` |
| [`layout-sistemi.md`](layout-sistemi.md) | Layout sistemi ayrıntıları | `Layout Sistemi.md` |

## Varyant planı — `ui-variant-plan/`

A–F varyant çerçevesi, bileşen envanteri, Figma/Storybook promptları ve
değerlendirme protokolü. 10 dosya: `00-genel-plan` → `09-bilesen-envanteri`.

Bu depoda **karşılığı olmayan** iki sözleşme (`10-frontend-katman-mimarisi`,
`13-foundation-contract`) referans implementasyonuyla birlikte hâlâ depo
dışındadır — bkz. [`../36-EXTERNAL-DESIGN-CORPUS.md`](../36-EXTERNAL-DESIGN-CORPUS.md).

## Kütüphane değerlendirmesi ve çalışma notları

| Dosya | İçerik |
|---|---|
| [`antdesign-how-to.md`](antdesign-how-to.md), [`antdesign.md`](antdesign.md) | Ant Design'ın rolü: standart yönetim ekranları için platform/SDK tabanı; üzerine tam CSS framework bindirilmez |
| [`gemini-components.md`](gemini-components.md), [`gemini-layout.md`](gemini-layout.md) | Bileşen ve layout araştırma çıktıları |
| [`figma-storybook-mcp-promptlari.md`](figma-storybook-mcp-promptlari.md), [`prompt.md`](prompt.md), [`prompt-2.md`](prompt-2.md) | Figma/Storybook MCP prompt taslakları |
| [`plan.md`](plan.md), [`plan-1.md`](plan-1.md), [`plan-2.md`](plan-2.md), [`codex-plan.md`](codex-plan.md) | Plan iterasyonları (tarihsel) |
| [`cikti.md`](cikti.md) | Form/data-table disiplini için altı kabul testi |
