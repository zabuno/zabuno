Evet, burada önemli bir felsefe var; fakat Material tarafında küçük bir düzeltme yapayım: **Material Design’ın temel ölçü sistemi `rem` değil, `dp` idi.** Material 1–3 boyunca ana geometri `dp`, Android tipografisi `sp` kullandı. Material 3’ün 2026 güncellemesinde de adaptif spacing hâlâ **8dp tabanlı**. ([Material Design][1])

Asıl kritik nokta şu:

> **2027 için ideal çözüm tek bir `rem`, `px`, `mm` birimi seçmek değil.
> İdeal çözüm: Design Token → Logical Unit → Platform Unit mimarisi.**

Ve senin tarif ettiğin enterprise SaaS / web / native ortak tasarım sistemi için ben **“her şeyi rem yapma” yaklaşımını önermem.**

## Önce `px` hakkındaki önemli yanlış anlaşılma

Bugünkü CSS'te:

**1 CSS `px` = 1 fiziksel ekran pikseli değildir.**

W3C, CSS `px` birimini bir **reference pixel / görsel açı birimi** olarak tanımlar. Retina veya yüksek DPI ekranda bir CSS pixel birden fazla fiziksel pixel kullanabilir. Media Query standardı da açıkça `px`'in physical pixel değil, **CSS pixel** olduğunu belirtir. ([W3C][2])

Örneğin kabaca:

```text
Normal ekran
1 CSS px ≈ 1 device pixel

Retina @2x
1 CSS px ≈ 2 × 2 physical pixel

@3x telefon
1 CSS px ≈ 3 × 3 physical pixel
```

Yani:

```css
width: 100px;
```

Retina'da fiziksel olarak 100 piksel çizilmek zorunda değildir.

Bu işi browser/devicePixelRatio zaten çözüyor.

Dolayısıyla **modern CSS `px`, eski düşündüğümüz “donanım pikseli” değildir.**

---

# Ben olsam 2027 sistemini böyle kurarım

| Tasarım alanı            | Tasarım sistemindeki kavram | Web                      | Android         | Apple                 | Flutter / RN      |
| ------------------------ | --------------------------- | ------------------------ | --------------- | --------------------- | ----------------- |
| Font size                | scalable type               | **rem**                  | **sp**          | **Dynamic Type / pt** | scaled logical px |
| Line height              | type ratio                  | unitless / `rem` / `rlh` | sp tabanlı      | Dynamic Type          | scalable          |
| Letter spacing           | type relative               | `em`                     | sp/em yaklaşımı | font metric           | relative          |
| Spacing                  | logical geometry            | **px/token**             | **dp**          | **pt**                | logical px        |
| Padding                  | logical geometry            | **px/token**             | dp              | pt                    | logical px        |
| Gap                      | logical geometry            | **px/token**             | dp              | pt                    | logical px        |
| Component height         | logical geometry            | **px/token**             | dp              | pt                    | logical px        |
| Radius                   | logical geometry            | **px/token**             | dp              | pt                    | logical px        |
| Border                   | stroke                      | **px**                   | dp              | pt/hairline           | logical px        |
| Shadow                   | effect token                | px                       | dp/elevation    | system/pt             | logical           |
| Icon                     | logical/icon                | px veya `em`             | dp              | pt/Symbol             | logical px        |
| Grid columns             | proportion                  | **fr / % / minmax**      | constraint      | flexible layout       | flex              |
| Component responsiveness | container                   | **cqi/cqw/@container**   | parent/window   | container             | constraints       |
| Viewport                 | environment                 | svh/dvh/etc.             | window          | window                | viewport          |
| Baskı                    | physical                    | mm/cm/pt                 | —               | —                     | —                 |

Android'ın resmi dokümantasyonu da tam olarak bu ayrımı yapıyor: geometri için `dp`, yazı için kullanıcı tercihlerine göre ölçeklenen `sp`; hatta **layout için sp kullanmayın** diyor. Apple ise Dynamic Type ile metnin kullanıcı tercihine göre ölçeklenmesini istiyor. ([Android Developers][3])

Bu nedenle **“her şeyi rem” cross-platform açısından aslında yanlış abstraction** olur.

---

# İdeal temel birim: `DU` — Design Unit

Burada `DU` diye CSS'e yeni bir birim önermiyorum.

Bu senin **design system içerisindeki soyut birimin** olsun.

Örneğin:

```text
1 DU = 4 logical unit
```

Ve sistem:

```text
1 DU  = 4
2 DU  = 8
3 DU  = 12
4 DU  = 16
5 DU  = 20
6 DU  = 24
8 DU  = 32
10 DU = 40
12 DU = 48
16 DU = 64
```

Ben özellikle senin data-dense enterprise SaaS yaklaşımında:

> **4-unit atomic grid + 8-unit primary rhythm**

kullanırdım.

Material'ın 8dp sistemiyle uyumludur ama 4'lük alt grid; table, input, chip, icon, dense form gibi yapılarda çok daha esnektir. Material'ın tarihsel sisteminde de genel layout 8dp, tipografi ve bazı ikon hizalamalarında 4dp alt grid kullanıldı. ([Material Design][4])

---

# Örneğin bir Input

Design token tarafında:

```text
field.height.md        = 40
field.radius.md        = 8

field.padding.inline   = 12
field.padding.block    = 8

field.border.width     = 1

icon.md                = 20

type.body.md           = 16
```

Bunlar **tasarım değerleri**.

Web renderer:

```text
height         → 40px
radius         → 8px
padding-inline → 12px
border         → 1px

font-size      → 1rem
```

Android renderer:

```text
height         → 40dp
radius         → 8dp
padding        → 12dp
border         → 1dp

font-size      → 16sp
```

Apple renderer:

```text
height         → 40pt
radius         → 8pt
padding        → 12pt

font           → Dynamic Type
```

React Native'de zaten boyutların unitless değerleri density-independent pixel mantığındadır; Flutter da fiziksel pikselden ayrı **logical pixel** kullanır. ([React Native][5])

Yani gerçek standart:

```text
40
```

olmalı.

`40px`, `40dp`, `40pt` ise **platform renderer'ın işi** olmalı.

---

# Peki `rem` nerede mükemmel?

**Typography'de.**

Örneğin:

```css
html {
    font-size: 100%;
}

body {
    font-size: 1rem;
}

small {
    font-size: 0.875rem;
}

h3 {
    font-size: 1.25rem;
}

h2 {
    font-size: 1.5rem;
}

h1 {
    font-size: 2rem;
}
```

W3C'ye göre `rem`, root element'in `em` değerine göre hesaplanır. Böylece component nesting'den kaynaklanan `em` compounding problemi olmaz. CSS Values Level 4 ayrıca `lh`, `rlh`, `cap`, `rcap`, `ch`, `rch` gibi daha semantik tipografik birimleri de tanımlıyor. ([W3C][2])

Ben:

```css
html {
    font-size: 62.5%;
}
```

gibi eski `1rem = 10px` numarasını da kullanmazdım.

Doğal browser ölçeğini korurdum:

```css
html {
    font-size: 100%;
}
```

---

# `em` ise icon için çok değerli

Bir ikon doğrudan yazıyla ilişkiliyse:

```css
.button-icon {
    width: 1.25em;
    height: 1.25em;
}
```

çok mantıklı.

Çünkü:

```text
Text büyür
↓
Icon büyür
↓
Button içindeki oran korunur
```

Ama bağımsız navigation icon:

```text
24 logical unit
```

olabilir.

Yani:

```text
inline icon → em

standalone UI icon → logical size token
```

ayrımı iyi olur.

---

# Grid için `rem` veya `px` bile merkezde olmamalı

2027'ye giderken esas değişim burada.

Şöyle düşünmek eski:

```css
.sidebar {
    width: 320px;
}

.content {
    width: 960px;
}
```

Daha doğru:

```css
.app {
    display: grid;
    grid-template-columns:
        minmax(14rem, 20rem)
        minmax(0, 1fr);
}
```

Daha da önemlisi artık component:

```css
@container (width > 40rem) {
    ...
}
```

şeklinde **ekrana değil içinde bulunduğu container'a göre** davranabiliyor.

CSS artık bunun için `cqw`, `cqh`, `cqi`, `cqb`, `cqmin`, `cqmax` birimlerini standartlaştırıyor. Örneğin `1cqi`, ilgili query container'ın inline ekseninin %1'i. ([W3C][6])

Bu bence 2030'a giden tasarım sistemlerinin en önemli fikirlerinden biri:

> Responsive Screen Design
> ↓
> Responsive Component Design

---

# `mm`, `cm`, `inch`, `pt` web UI için neden yanlış?

Çünkü ekranda bunların fiziksel karşılığına güvenemezsin.

W3C ekranlarda anchor olarak fiziksel santimetre yerine reference pixel kullanılmasını öneriyor; bu nedenle CSS'teki `1mm` her cihazda cetvelle ölçtüğünde gerçekten 1 mm olmak zorunda değil. Fiziksel birimler daha çok **çıktı ortamı bilinen print** senaryoları için uygundur. ([W3C][2])

Dolayısıyla:

```text
Screen UI:

mm     HAYIR
cm     HAYIR
inch   HAYIR
pt     Web'de genellikle HAYIR

px     EVET
rem    EVET
em     EVET
fr     EVET
%      EVET
cq*    EVET
```

---

# Border özel bir durum

Burada ben `rem` kullanmam.

```css
border: 0.0625rem solid;
```

yerine:

```css
border: 1px solid;
```

daha doğru.

Çünkü amacı:

> “font büyüdüğünde border da kalınlaşsın”

değil.

Amaç:

> “UI yüzeyleri arasında ince bir stroke olsun.”

Dolayısıyla:

```text
border.hairline = 1 logical unit
border.default  = 1
border.strong   = 2
```

gibi davranmalı.

Retina'da browser bunun rasterization'ını zaten fiziksel piksel yoğunluğuna göre yapar.

---

# Radius da tamamen `rem` olmamalı

Örneğin font büyüdüğü için:

```text
8px radius
↓
12px
↓
16px
```

olması zorunlu değil.

Radius, font değil **shape semantic**'idir.

Daha iyi:

```text
radius.none = 0
radius.xs   = 2
radius.sm   = 4
radius.md   = 8
radius.lg   = 12
radius.xl   = 16
radius.2xl  = 24
radius.full = 9999
```

Sonra platform adapter:

```text
Web       → px
Android   → dp
iOS       → pt
Flutter   → logical px
```

---

# Shadow için de aynı şey

Shadow:

```text
shadow-1
shadow-2
shadow-3
shadow-4
```

gibi **semantic elevation token** olmalı.

Şunu component içine gömmek:

```css
box-shadow:
    0 12px 32px rgba(...);
```

yerine:

```css
box-shadow: var(--elevation-raised);
```

2030'a daha dayanıklı.

Çünkü Apple, Android, Web ve hatta future XR renderer aynı `elevation-raised` semantiğini farklı şekilde render edebilir.

---

# 2027 trendi aslında çok netleşmiş durumda

2025 Ekim'inde Design Tokens Community Group ilk stabil Design Tokens Format Specification'ı yayımladı. Amaç tam olarak design decisions'ı web, iOS, Android, Flutter ve tasarım araçları arasında taşınabilir bir **single source of truth** haline getirmek. 2026 itibarıyla Figma, Penpot, Sketch, Tokens Studio ve çeşitli tooling'lerin bu standardı desteklediği belirtiliyor. ([W3C][7])

Daha da ilginci stabil DTCG `dimension` token'ında şu anda yalnızca:

```text
px
rem
```

birimlerini standart interchange formatında kabul ediyor. ([Design Tokens][8])

Bu çok önemli.

Çünkü burada `px`:

> Android'a da `px` basın

anlamına gelmiyor.

Token pipeline:

```text
Design Token
        ↓
Semantic resolution
        ↓
Platform transformation
        ↓
Web / Android / iOS / Flutter
```

şeklinde çalışıyor.

---

# 2030 yönü

2030'a giderken bence ölçü sistemi kabaca şuna dönüşecek:

```text
Eskiden

screen
→ breakpoint
→ px


Şimdi

viewport
→ rem
→ responsive


2027+

container
→ semantic token
→ density
→ accessibility
→ platform


2030+

context
→ capability
→ user preference
→ interaction mode
→ distance
→ container
→ adaptive token resolver
```

Bu tahminin işaretleri şimdiden var: DTCG'nin 2026'daki Resolver çalışması token değerlerinin `mobile / tablet / desktop`, light/dark ve accessibility gibi farklı **contexts** altında çözümlenmesini ele alıyor. Bu kısım henüz preview ve uygulanması tavsiye edilen stabil standart değil; fakat yönü çok açık gösteriyor. ([Design Tokens][9])

Apple da sistemlerini ekran boyutu ve input mode'lar arasında ortak/adaptif tasarım temeline taşıdığını açıkça vurguluyor. ([Apple Developer][10])

---

# 2035'te “pixel perfect” kavramı daha da anlamsızlaşır

Tahminim:

```text
Pixel perfect
```

yerine:

```text
Perceptual consistency
Interaction consistency
Semantic consistency
```

önemli olacak.

Çünkü aynı uygulama:

```text
phone
tablet
desktop
foldable
TV
car
AR/XR
external monitor
touch
mouse
pen
gaze
```

ortamlarında yaşayacak.

Apple bugün bile klasik touch için 44×44pt, visionOS için 60×60pt gibi farklı hit-region önerileri kullanıyor. Yani **aynı component semantiğinin fiziksel presentation'ı interaction context'e göre değişmeye başladı bile.** ([Apple Developer][11])

2035 tasarım sisteminin amacı:

> “Her yerde aynı sayıda pixel”

değil,

> **“Her yerde aynı algısal ve etkileşimsel hiyerarşi”**

olacak.

---

# O halde senin design system için kararım

Tek cümleyle:

> **Font = `rem`; UI geometry = logical design token; Web renderer = CSS px; Android = dp; Apple = pt; responsive layout = fr/%/container units; accessibility = ayrı scale katmanı.**

Ben senin sisteminde şu 7 kuralı sabitlerdim:

1. **Base atomic grid = 4 logical units**, ana ritim = 8.
2. Spacing, padding, height, radius, icon geometry'sini `rem`'e bağlama.
3. Web geometry output'u ağırlıklı olarak CSS `px` olsun; zaten density-independent/reference pixel mantığında çalışır. ([W3C][2])
4. Typography `rem/em`; Android'da `sp`; Apple'da Dynamic Type.
5. Grid ölçüsü sabit birim değil, `fr + minmax + % + container query` tabanlı olsun.
6. `mm/cm/in` yalnızca print/physical output için kullanılsın.
7. **Component hiçbir zaman doğrudan `8px`, `16px`, `12px radius` bilmesin; sadece semantic token bilsin.**

Sonuncusu en önemlisi.

Yani:

```css
padding: 12px;
border-radius: 8px;
height: 40px;
```

değil:

```css
padding-inline: var(--field-padding-inline);
border-radius: var(--field-radius);
min-height: var(--field-height-md);
```

Ve geride:

```text
Primitive Tokens
        ↓
Semantic Tokens
        ↓
Component Tokens
        ↓
Context Resolver
        ↓
Platform Adapter
        ↓
Web / Android / iOS / Flutter
```

olsun.

**Bence 2027–2035 için asıl “Material Design'ın dp devriminin” devamı `rem` değil, bu mimari: *unit-independent semantic design tokens*.** Bu yaklaşım, senin tarif ettiğin global enterprise SaaS için en uzun ömürlü çözüm olur. ([W3C][7])

[1]: https://m3.material.io/foundations/layout/grids-spacing/density?utm_source=chatgpt.com "Grids & spacing – Material Design 3"
[2]: https://www.w3.org/TR/css-values-4/?utm_source=chatgpt.com "CSS Values and Units Module Level 4"
[3]: https://developer.android.com/training/multiscreen/screendensities?utm_source=chatgpt.com "Support different pixel densities | Compatibility"
[4]: https://m1.material.io/layout/metrics-keylines.html?utm_source=chatgpt.com "Metrics & keylines - Layout"
[5]: https://reactnative.dev/docs/height-and-width?utm_source=chatgpt.com "Height and Width · React Native"
[6]: https://www.w3.org/TR/css-contain-3/?utm_source=chatgpt.com "CSS Containment Module Level 3"
[7]: https://www.w3.org/community/design-tokens/2025/10/28/design-tokens-specification-reaches-first-stable-version/?utm_source=chatgpt.com "Design Tokens specification reaches first stable version"
[8]: https://www.designtokens.org/TR/2025.10/format/ "Design Tokens Format Module 2025.10"
[9]: https://www.designtokens.org/tr/drafts/resolver/?utm_source=chatgpt.com "Design Tokens Resolver Module 2025.10"
[10]: https://developer.apple.com/videos/play/wwdc2025/356/?utm_source=chatgpt.com "Get to know the new design system - WWDC25 - Videos"
[11]: https://developer.apple.com/design/human-interface-guidelines/buttons?utm_source=chatgpt.com "Buttons | Apple Developer Documentation"
