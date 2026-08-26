# 06 — Figma MCP Prompt Kataloğu

Bu dosya, EA Platform çok-varyant UI planının Figma tarafındaki tüm üretim işlerini yürüten Figma MCP prompt kataloğudur. Kapsam: kütüphane envanteri ve audit, Variables kurulumu (primitive / semantic / density / variant overlay), component set üretimi (`variant` property A–F), 6-lı karşılaştırma canvas'ı, prototip bağlantıları ve Code Connect eşlemesi. Prompt'lar İngilizce'dir ve brief'in §1 değişmezleri ile §2 varyant tanımlarını değer düzeyinde (hex, px, rem, ms) gömülü taşır; prompt'u çalıştıran ajan doğaçlama değer üretmez, brief dışı varyant icat etmez. Çalışma sırası bağlayıcıdır: önce envanter/audit, sonra üretim.

Bağlantılı dosyalar: [00-genel-plan.md](./00-genel-plan.md) · [01-varyant-cercevesi.md](./01-varyant-cercevesi.md) · [02-card-varyantlari.md](./02-card-varyantlari.md) · [03-form-varyantlari.md](./03-form-varyantlari.md) · [04-table-varyantlari.md](./04-table-varyantlari.md) · [05-bilesen-varyantlari.md](./05-bilesen-varyantlari.md) · [07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md) · [08-degerlendirme-protokolu.md](./08-degerlendirme-protokolu.md)

## 1. Çalışma sırası ve ihlal edilemez kurallar

| Sıra | Adım | Araç(lar) | Kural |
|---|---|---|---|
| 1 | Envanter ve audit | `get_metadata` → `get_design_context` (scope'lu) → `get_variable_defs` → `get_code_connect_map` | Üretimden önce ZORUNLU. Tüm dosyaya `get_design_context` çağrılmaz; önce `get_metadata` ile ağaç/node ID çıkarılır, sonra yalnız ilgili node'a `get_design_context` ile scope daraltılır. |
| 2 | Skill okuma | `/figma-use` (fallback: `skill://figma/figma-use/SKILL.md`) | Her `use_figma` çağrısından ÖNCE okunur; okumadan `use_figma` çağrılmaz. |
| 3 | Üretim | `use_figma` | Yalnız audit raporu çıktıktan sonra. Mevcut ve Code Connect eşlemesi olan bir bileşen ASLA yeniden yaratılmaz; mevcut set genişletilir. |
| 4 | Fidelity kontrol | `get_screenshot` | Her üretim adımından sonra ekran görüntüsü alınır, brief değerleriyle (radius, border, renk, tipografi) karşılaştırılır; sapma varsa düzeltilip tekrar doğrulanır. |
| 5 | Eşleme | `get_code_connect_map` → `add_code_connect_map` | Eşleme yazmadan önce mevcut harita okunur; çakışan eşleme üzerine yazılmaz, raporlanır. |

Ek kurallar (tüm prompt'larda geçerli):

- Hardcode hex yasak: her fill/stroke/text rengi bir variable'a bağlanır (semantic → primitive alias zinciri).
- Yüzey/kontrol radius tavanı 8px'tir (12px yalnız kullanıcının mutlak üst limiti olarak anılır; operasyonel tavan 8px). Input alanları muaftır: varyanta göre 0px, 4px veya pill (pill yalnız Variant F input'ları ve badge/tag/avatar/switch kapsül bileşenleri).
- Tipografi: Roboto, yalnız 400/500/700; minimum 1rem (16px) — tablo başlığı ve caption dahil; sayısal hücrelerde `tabular-nums`.
- Glass/blur form, tablo, veri yüzeyi ve scrim'de yasak; scrim düz %40 ink/950. Hover'da scale yok. Motion 120–240ms, ease-out, `prefers-reduced-motion` destekli.
- İkon: Phosphor, SVG; emoji yasak. Label her zaman görünür ve input'un üstünde; placeholder'a gömülmez.

### 1.1 Fidelity kontrol döngüsü

Her üretim prompt'unun sonunda aynı döngü işletilir:

1. `get_screenshot` ile üretilen frame/set görüntülenir (A–F kolonları ayrı ayrı).
2. Görüntü, ilgili prompt'un gömülü değerleriyle karşılaştırılır: radius, border kalınlığı, divider stratejisi, focus stili, input biçimi, renkler.
3. Sapma varsa `use_figma` ile düzeltilir; düzeltme sonrası tekrar `get_screenshot`.
4. Temiz görüntü alınmadan bir sonraki bileşen/aileye geçilmez.

### 1.2 Sık hata / anti-pattern tablosu

| Anti-pattern | Doğru davranış |
|---|---|
| Dosya köküne `get_design_context` çağırmak | Önce `get_metadata` ile node ID'ler; context yalnız hedef node'a |
| `/figma-use` okumadan `use_figma` çağırmak | Skill her oturumda, ilk `use_figma`'dan önce okunur |
| Code Connect eşlemeli bileşeni silip yeniden kurmak | Mevcut set yerinde genişletilir; çakışma raporlanır |
| Varyantları 6 ayrı component set olarak kurmak | Tek set + `variant` property A–F |
| Tema/density'yi property olarak eklemek | Tema ve density variable MODE'larıdır; property yalnız `variant` ve `state` |
| Instance detach edip canvas'ta serbest kopya üretmek | Karşılaştırma canvas'ı dahil her yerde bağlı instance kullanılır |
| Screenshot almadan sonraki aileye geçmek | §1.1 döngüsü her adımda zorunlu |

## 2. Varyant hızlı referansı (prompt tutarlılık tablosu)

Prompt'lara gömülen varyant değerlerinin tek bakışta kaynağı. Tam tanımlar için [01-varyant-cercevesi.md](./01-varyant-cercevesi.md).

| Varyant | Yüzey radius | Input biçimi (muafiyet) | Focus-visible | Ayrım grameri | Divider | Density varsayılanı |
|---|---|---|---|---|---|---|
| A "Hairline" | sm (4px) | 0px keskin, 1px border | 2px ring (light #003399 / dark #93A8F4), offset 2px | 1px semantik border; ton farkı yok, gölge yok | 1px tam genişlik | compact'a yakın standard |
| B "Tonal" | lg (8px) | filled-tonal, 4px, border yok; focus'ta ring + 2px mavi alt kenar | 2px ring + zemin bir ton aydınlanır | border'sız ton basamakları (dark 950→900→800; light 50→beyaz) | yok — spacing ayırır | comfortable'a yakın standard |
| C "Stripe" | md (6px) | 0px keskin, 1px border; focus'ta 2px mavi start şeridi + ring | start şeridi 2px mavi + 2px ring | %50 opak 1px border + 2px logical-start durum şeridi | 1px inset (içerik bölgesi) | standard |
| D "Inset" | dış lg (8px), iç 0px | borderless-inset, bir ton zemin, 4px; focus'ta inset ring | inset ring 2px (taşma yok) | gruplanmış yüzey; dış 1px border + bir ton fark | iç ayraç 1px tam genişlik | comfortable |
| E "Rule" | xs (2px) | 1px border, 0px keskin, alt kenar 2px | 2px ring + altçizgi kalınlaşması (etkileşimli metin) | kutu minimum; tipografi + yatay rule | bölüm 2px / öğe 1px | standard |
| F "Elevated" | lg (8px) | pill-shape, 1px border, iç padding start/end 20px | 2px ring + gölge derinleşir | gölge y=2 blur=8 %10 siyah (dark: bir ton + 1px border) | yok (kart içi gerekiyorsa 1px) | standard |

## 3. Prompt kataloğu

Katalog numarası çalıştırma sırası DEĞİLDİR; P2 üretim sırası
[00-genel-plan.md](./00-genel-plan.md)'de tanımlandığı gibi card → form → table →
destekleyici bileşenlerdir (yani P-06-4, P-06-3'ten önce çalıştırılır).
Faz eşlemesi için §4 tablosuna bakın.

### 3.1 P-06-1 — Kütüphane envanter + audit

Ne zaman kullanılır: Her Figma çalışma oturumunun BAŞINDA ve her üretim prompt'undan önce; mevcut dosyanın durumunu, Code Connect eşlemelerini ve brief ihlallerini çıkarmak için. Bu prompt hiçbir düzenleme yapmaz.

```text
Role: design-system auditor for the EA Platform library. READ-ONLY pass: no edits.

Workflow (strict order, keep scope narrow):
1. Call get_metadata on the file root to map pages, top-level frames, component sets
   and their node IDs. Never call get_design_context on the whole file.
2. For each relevant library page (tokens, components, patterns, comparison canvases),
   call get_design_context scoped to that page or frame node ID only.
3. Call get_variable_defs to list existing variable collections, modes, and aliases.
4. Call get_code_connect_map to list every component that already has a Code Connect
   mapping. Mark each one DO-NOT-RECREATE: existing mapped components must be extended
   in place, never rebuilt.

Produce an audit report with two tables.
Table 1 — Inventory: component/set name | node ID | variant properties present
(expect `variant` = A..F) | states covered | variable-bound vs hardcoded values |
Code Connect mapped (yes/no).
Table 2 — Violations, checked against these binding rules:
- Typography: font must be Roboto (Latin) with Noto Sans script fallbacks, self-hosted;
  weights only 400/500/700 (below 400 forbidden); minimum text size 1rem (16px)
  including table headers and captions; numeric cells use tabular-nums.
- Color: any hardcoded hex instead of a variable binding is a violation.
  Text on #FFB900 must be #080616, never white
  (#FFB900/#FFFFFF ~ 1.72:1 FAIL; #FFB900/#080616 ~ 11.63:1 AAA).
  #003399 must never appear as text or thin icon on #080616 (1.85:1);
  on dark, text-level blue must be #93A8F4; #003399 on dark only as wide fill,
  border, or structural surface.
- Geometry: corner radius above 8px on surfaces/controls is a violation
  (12px is the user's absolute limit but 8px is the fixed operational ceiling).
  Inputs are exempt: 0px, 4px, or pill depending on variant; pill only on
  Variant F inputs and capsule components (badge, tag, avatar, switch).
  Spacing must come from the 4/8/12/16/24/32/48 scale.
  Hit areas below 44x44px are violations.
- Effects: any blur/glass on forms, tables, data surfaces, or scrims;
  any hover scale; any shadow outside Variant F's allowed set
  (raised y=2 blur=8 10% black; overlay y=4 blur=16 12%).
- States: any status communicated by color alone (icon + text required);
  any component missing a focus-visible style; labels placed inside placeholders.
End with a prioritized fix list. Do not fix anything in this pass.
```

### 3.2 P-06-2 — Variables kurulumu (primitive / semantic / density / variant overlay)

Ne zaman kullanılır: P0 fazında bir kez, audit temiz raporlandıktan sonra; dört variable koleksiyonunu kurmak veya drift varsa brief değerlerine geri çekmek için.

```text
Before any use_figma call, read the /figma-use skill
(fallback: skill://figma/figma-use/SKILL.md).
First run the inventory prompt; if collections already exist, reconcile them to the
values below instead of duplicating. Then create/update exactly four collections.

Collection "primitive" (single mode; raw values live ONLY here):
- color/yellow/500 = #FFB900; color/blue/700 = #003399; color/blue/300 = #93A8F4
- color/ink/950 = #080616; color/ink/900 = #0D0A24; color/ink/800 = #16123A
- color/ink/50 = #F7F7FB; color/white = #FFFFFF
- color/border/light = #E4E4EE; color/border/dark = #26224A
- color/success/600 = #15803D; color/error/600 = #DC2626
- color/warning/600 = #B45309; color/info/600 = #1D4ED8
- radius/xs = 2; radius/sm = 4; radius/md = 6; radius/lg = 8; radius/pill = 999
  (radius/lg is the surface/control ceiling; radius/pill is reserved for Variant F
  inputs and capsule components: badge, tag, avatar, switch)
- space/1..7 = 4, 8, 12, 16, 24, 32, 48
- size/touch-target-min = 44; size/icon-min = 20; size/icon-max = 24
- type/family = Roboto (script fallback: Noto Sans families, self-hosted, no CDN)
- type/weight = 400, 500, 700 only; type/size-min = 16 (1rem)
- motion/fast = 120ms; base = 150ms; slow = 200ms; max = 240ms; easing = ease-out

Collection "semantic" (modes: light, dark; ALIASES ONLY, no raw hex):
- surface/canvas: light -> ink/50, dark -> ink/950; surface/raised: light -> white,
  dark -> ink/900; surface/overlay: light -> white, dark -> ink/800
- border/default: light -> border/light (#E4E4EE), dark -> border/dark (#26224A)
- text/primary: light -> ink/950, dark -> white at 87%
- text/secondary: dark -> white at 60%; light -> derived from the ink scale in P0
  and contrast-verified >= 4.5:1 before use
- accent/primary = yellow/500 in both modes;
  text/on-accent = ink/950 (#080616) in both modes — never white on yellow
- accent/text: light -> blue/700 (#003399), dark -> blue/300 (#93A8F4).
  Hard rule: blue/700 never as text or thin icon on dark surfaces; on dark it may
  only be wide fill, border, or structural surface
  (token accent/structural = blue/700, both modes)
- status/success|error|warning|info: light -> the /600 primitives; dark -> lightened
  derivatives produced in P0, each verified >= 4.5:1 on ink/950
  (status is never conveyed by color alone — always pair with icon + text)
- focus/ring: light -> blue/700, dark -> blue/300
- scrim = ink/950 at 40%, flat, no blur
  (glass/blur forbidden on forms, tables, data surfaces, scrims)

Collection "density" (modes: comfortable, standard, compact):
- row/height = 52 / 44 / 36; density changes padding, metadata visibility, and
  column visibility only — NEVER font size (type/size-min = 16 holds in all modes)

Collection "variant-overlay" (modes: a, b, c, d, e, f — exactly six, no additions;
lowercase mode names match the code-side data-variant attribute, while the Figma
component property spells variants uppercase A-F):
- radius/surface = 4 / 8 / 6 / 8 (outer; inner items 0) / 2 / 8
- radius/input = 0 / 4 / 0 / 4 / 0 / pill
- container/border-width = 1 / 0 / 1 (border token at 50% opacity) /
  1 (outer container only) / 0 / 0 in light (shadow instead),
  1 in dark (tone + border replaces shadow).
  Exception: mandatory boxes (Menu/Dropdown popover, Modal) always use
  border/default at 1px in every mode, independent of container/border-width —
  mode e's 0 value never removes a popover's box
- stripe/width: c = 2 at logical start (selected = blue, error = error/600,
  success = success/600, AI-provenance = yellow/500); all other modes = 0
- divider = a: 1px full width / b: none (spacing separates) /
  c: 1px inset within content region / d: 1px full width inside group /
  e: 2px section + 1px item / f: none (1px inside card when needed)
- focus/ring-width = 2 in all modes; focus/extra = a: offset 2 /
  b: surface lightens one step / c: 2px blue start stripe joins the ring /
  d: inset ring, no overflow / e: underline thickens on interactive text /
  f: shadow deepens
- density/default = standard (compact-leaning) / standard (comfortable-leaning) /
  standard / comfortable / standard / standard
- shadow: f only — raised y=2 blur=8 black 10%; overlay y=4 blur=16 black 12%;
  hover y=4 blur=12; dark mode replaces shadow with one tone step + 1px border.
  All other modes: no shadow.
- motion: a = 120ms opacity/color only, no hover animation /
  b = 150ms background, 200ms panel slide+fade /
  c = stripe appears 120ms scaleY (instant under reduced motion) /
  d = 200ms height+fade expand/collapse / e = 150ms content fade only /
  f = 200ms shadow/color, 240ms card fade + 4px rise (fade only under reduced
  motion). Never scale on hover in any mode.

After creation, call get_variable_defs and diff against this spec; fix any drift.
Then get_screenshot the token documentation frame to confirm swatches render the
exact hex values above.
```

### 3.3 P-06-3 — Form component set üretimi (variant prop A–F)

Ne zaman kullanılır: P2 fazında, Variables kurulumu doğrulandıktan ve form ailesi için audit "yeniden yaratma yok" onayı verdikten sonra. Detaylı state ve davranış modeli için [03-form-varyantlari.md](./03-form-varyantlari.md).

```text
Read the /figma-use skill before calling use_figma. Run the inventory prompt first;
any form component with an existing Code Connect mapping must be extended, not recreated.

Build/extend the Form family as component sets, one set per component:
TextField, TextArea, Select/Combobox, Checkbox, Radio, Switch, DateField,
SearchField, FileUpload, FormSection, FormFooter, ErrorSummary.
Properties on every set: variant = A|B|C|D|E|F (single set per component — six
micro-style interpretations of one grammar, not six libraries);
state = default|hover|focus|filled|error|disabled|readonly|loading.
Theme (light/dark) and density (comfortable/standard/compact) come from variable
modes, not properties.

Shared invariants (bind everything to variables, no raw hex):
- Label: always visible, above the field, never inside the placeholder,
  never floating. Roboto, minimum 16px.
- Hit area min 44x44px; visible icons 20-24px (Phosphor, SVG).
- Error state = icon + text + color, never color alone;
  text contrast >= 4.5:1 in every state.
- RTL-safe: logical start/end alignment and padding so the layout mirrors.
- No blur, no hover scale.

Per-variant input shape (the radius-exemption axis) and label treatment:
- A: sharp 0px, 1px border/default; focus = 2px ring
  (light #003399 / dark #93A8F4) with 2px offset;
  hover darkens border + 4% surface tint; label Roboto 500 above.
- B: filled-tonal (surface one tone darker/lighter), 4px radius, no border;
  focus = 2px ring + 2px blue bottom edge;
  label Roboto 500 outside the filled area, above.
- C: sharp 0px, 1px border default; focus = 2px blue logical-start stripe + 2px ring;
  required fields marked with the text "(zorunlu)", never an asterisk alone;
  label Roboto 500.
- D: borderless-inset — background one tone darker/lighter, 4px radius;
  focus = 2px inset ring with no overflow; label hierarchy may invert
  (label 400 / value 500) for property-editor semantics.
- E: 1px border, sharp 0px, bottom edge 2px for editorial weight; focus = 2px ring;
  label Roboto 500, section headings 700; never use 700 at reduced size —
  the 1rem minimum holds.
- F: pill-shape (full round, radius/pill) — the only variant whose inputs may be
  pill; 1px border; focus = 2px ring; inner padding logical start/end = 20px;
  label Roboto 500 OUTSIDE the pill, above — never floating or inside.

Layout: single-column form composition by default (exceptions per the 03 file).
Add validation storyboard frames: value preserved -> explanation -> correction ->
confirmation.
After building each set, get_screenshot per variant column (A-F x key states),
verify radius, border, focus, and label values; fix drift before the next component.
```

### 3.4 P-06-4 — Card component set üretimi (variant prop A–F)

Ne zaman kullanılır: P2 fazında, planlanan aile sırasının başında (card → form → table); kart anatomisi ve tür detayları için [02-card-varyantlari.md](./02-card-varyantlari.md).

```text
Read the /figma-use skill before calling use_figma. Inventory first; extend any
Code Connect-mapped card in place, never recreate it.

Build/extend the Card family as one component set with slots:
container / header / media / body / meta / actions.
Properties: variant = A|B|C|D|E|F;
type = metric-kpi | entity | list-item | form-section | commerce;
selected = true|false. Theme and density via variable modes.
Bind all colors, radii, spacing to the variant/semantic/density collections.

Per-variant container grammar (separation, radius, hover, selected):
- A "Hairline": 1px semantic border; surface tone identical to canvas
  (no tonal difference); no shadow; radius 4px;
  hover = border darkens + 4% tint;
  selected = 2px blue border + thin logical-start marker — border only,
  no 8% blue fill.
- B "Tonal": no border; separation by tone steps
  (dark ink/950 -> ink/900 -> ink/800; light ink/50 -> white); radius 8px;
  hover = surface lightens one tone (150ms background-color);
  selected = 8% blue fill (light #003399 at 8%, dark #93A8F4 at 10%) + icon.
- C "Stripe": 1px border at 50% opacity + 2px logical-start status stripe
  (selected = blue, error = #DC2626, success = #15803D,
  AI-provenance = #FFB900); radius 6px;
  hover = stripe appears at 40% opacity + 4% tint;
  selected = 2px blue start stripe + 6% blue fill.
- D "Inset": one large grouped surface; outer container 1px border + one tone
  difference, radius 8px; inner items 0px radius, separated by 1px full-width
  inner dividers; hover = inner row fills one tone;
  selected = row fill + check icon at logical end.
- E "Rule": minimal boxes — structure from typographic hierarchy + horizontal
  rules (2px section / 1px item); radius 2px, rarely visible;
  hover = text darkens, no surface tint (3% tint only on row-based items);
  selected = 2px rule above+below or bold start marker, minimal fill.
- F "Elevated": raised shadow y=2 blur=8 black 10%
  (dark mode: one tone step + 1px border instead of shadow); radius 8px;
  hover = Z-lift, shadow deepens to y=4 blur=12 — NO scale, NO translate;
  selected = persistent elevation + 1px blue border;
  card entrance 240ms fade + 4px rise (fade only under reduced motion).

Constraints repeated for emphasis: surface radius ceiling 8px;
yellow #FFB900 only per each variant's accent dose
(A/E: primary CTA only; C: AI-provenance stripe; F: CTA + focus metrics);
text on yellow always #080616; status always icon + text.
Produce a 320px-wide behavior frame per card type. After each type, get_screenshot
the A-F row and verify values; fix drift immediately.
```

### 3.5 P-06-5 — Data table component set üretimi (variant prop A–F)

Ne zaman kullanılır: P2 fazında card ve form ailelerinden sonra; hücre tipleri, mobil stratejiler ve performans hedefleri için [04-table-varyantlari.md](./04-table-varyantlari.md).

```text
Read the /figma-use skill before calling use_figma. Inventory first;
never recreate a Code Connect-mapped table part.

Build/extend the DataTable family with regions:
header / row / cell / toolbar / bulk-actions / pagination / empty /
loading-skeleton / error / inline-edit / tree-row.
Cell types: text, number, currency, date, status-badge, user, actions, checkbox.
Properties: variant = A|B|C|D|E|F; density and theme via variable modes
(row heights: comfortable 52 / standard 44 / compact 36 — density never shrinks
fonts; it reduces padding, metadata, and column visibility).

Shared invariants:
- No zebra striping — 1px separator + hover fill is the base grammar.
- Header and caption text minimum 1rem (16px); numeric cells use tabular-nums.
- Alignment: text -> logical start, number/currency and actions -> logical end.
- Selection/checkbox targets min 44x44px.
- Status cells = icon + text, never color alone.

Per-variant header, row separation, and selection:
- A: header transparent, 1px bottom rule thickening to 2px when sticky,
  text 1rem/500; rows separated by 1px full-width dividers;
  selected row = 2px blue border + thin start marker — border only, no blue fill;
  hover = border darkens + 4% tint.
- B: header band one tone different, top corners 8px, text 1rem/500;
  no row dividers — spacing and tone separate;
  selected = 8% blue fill (dark: #93A8F4 at 10%) + icon;
  hover = row lightens one tone (150ms).
- C: header transparent; sorted column gets a 2px bottom stripe on its header
  cell; row states carried by the 2px logical-start stripe (selected = blue +
  6% fill, error = #DC2626, success = #15803D, AI-provenance = #FFB900);
  dividers 1px inset (start-aligned inside the content region).
- D: header = one tone darker band of the group surface; table outer shell 8px
  radius, cells 0px; inner 1px full-width dividers;
  selected = row fill + check icon at logical end; hover = row fills one tone.
- E: header between double rules (2px top + 1px bottom), transparent background,
  text 1rem/700; rows separated by 1px rules, sections by 2px;
  selected = 2px rules above+below, minimal fill;
  hover = text darkens, 3% row tint max.
- F: card-embedded table — header band one tone + table shell part of the card
  (8px); selected row = 1px blue border + one-tone fill (elevation stays at the
  card level — per-row shadow is forbidden);
  card hover deepens shadow (y=4 blur=12), row hover = fill; no scale.

Also produce mobile-strategy frames at 320px per the 04 file (priority columns /
row summary + detail / stacked / controlled horizontal). Annotate the 10k-row
virtualization target (60fps, main-thread < 50ms) as a design note on the
loading-skeleton frame — Figma does not test it, code does (see 07).
After each region, get_screenshot the A-F matrix and verify divider widths,
header treatment, and selection marks against this prompt.
```

### 3.5b P-06-5b — Destekleyici bileşen component set üretimi (variant prop A–F)

Ne zaman kullanılır: P2 fazında table ailesinden sonra, kompozisyon ekranlarına (P3) geçmeden önce; 12 bileşen grubunun eksen kararları için [05-bilesen-varyantlari.md](./05-bilesen-varyantlari.md).

```text
Read the /figma-use skill before calling use_figma. Inventory first;
never recreate a Code Connect-mapped component.

Build/extend the supporting component families, each as ONE component set with
variant = A|B|C|D|E|F (theme and density via variable modes):
Button (primary / secondary / ghost / danger), IconButton, Badge / Tag / Status,
Tabs, SegmentedControl, Toolbar, SidePanel (left Option Gallery + right Option
Information), Menu / Dropdown, Toast / Alert, Modal / Drawer, Pagination,
CommandPalette.

Shared invariants (bind every value to the variables from the variables-setup
prompt; the per-variant axis decisions live in the 05 file and must be applied
value-for-value):
- Primary button: background #FFB900, text #080616 in BOTH themes — never white
  on yellow. Yellow is reserved for primary CTA and AI-provenance marks.
- Blue #003399 never as text or thin icon on dark surfaces; text-level blue on
  dark is always #93A8F4.
- Badge/Tag/Status and Switch are capsule components: pill radius allowed there
  (and nowhere else outside Variant F inputs). Status = icon + text, never
  color alone.
- Scrim behind Modal/Drawer/SidePanel: flat ink/950 at 40%, no blur.
- Glass/translucency: optional ONLY on CommandPalette and global chrome, always
  with an opaque fallback variant; forbidden on every other surface.
- Mandatory boxes (Menu/Dropdown popover, Modal) keep a 1px border/default box
  in every variant — Variant E's zero container border does not apply to them.
- All touch targets min 44x44px; visible icons 20-24px; text min 1rem,
  weights 400/500/700 only.
- Motion per variant as defined in the variant-overlay collection; never scale
  on hover.

Produce a 320px frame per family (SidePanel becomes full-screen sequential
panels at 320px; Toolbar wraps; Tabs scroll horizontally inside their own
container). After each family, get_screenshot the A-F row, verify against the
05 axis tables, and fix drift before moving to the next family.
```

### 3.6 P-06-6 — 6-lı karşılaştırma canvas'ı (aynı ekran × A–F × 320px + 1440px)

Ne zaman kullanılır: P3 fazında, üç bileşen ailesi de component set olarak hazır olduğunda; [08-degerlendirme-protokolu.md](./08-degerlendirme-protokolu.md) değerlendirme turlarının görsel girdisini üretmek için.

```text
Read the /figma-use skill before calling use_figma. Inventory first: reuse the
existing Card/Form/DataTable component sets via their variant property —
do NOT detach instances or rebuild components on the canvas.

Create a comparison page per reference screen (start with "Kayit Listesi" record
list; later "Kayit Olustur" and "Detay + Inline Edit").
Layout: a 6 x 2 grid — columns = variants A..F, rows = viewport widths 320px and
1440px (breakpoint band: 320/480/768/1024/1440; mobile-native first — compose the
320px row first). Same screen content in all 12 frames; only the variant mode of
the variant collection differs per column.
Duplicate the page once with dark mode applied (light and dark both first-class).

Composition rules:
- Every frame uses instances bound to semantic/density/variant variables.
- Density set to each variant's default (A standard compact-leaning,
  B standard comfortable-leaning, C standard, D comfortable, E standard,
  F standard).
- Primary CTA is yellow #FFB900 with #080616 text in ALL variants.
- No blur, no hover-scale artifacts baked into frames.

Annotation layer (outside the frames, linked with connector lines): for each
variant column add a trade-off note card covering at minimum:
- scan speed on dense data; contrast/a11y risk points;
- render-cost implications (shadow vs border vs tone);
- state-communication clarity (how selected/error/AI-provenance read);
- density flexibility; i18n/RTL resilience (German expansion, Arabic mirror).
Reference the 12 micro-axes by number where relevant. Keep annotations factual;
scoring happens in the evaluation protocol (file 08), not here.

Verification: get_screenshot each column (both widths, both themes) and check:
correct radius per variant (A 4 / B 8 / C 6 / D 8-outer-0-inner / E 2 / F 8),
correct input shape (A/C/E sharp 0px, B/D 4px, F pill),
correct separation grammar, no text below 16px, no white text on yellow,
no #003399 text on dark. Fix and re-screenshot until clean.
```

### 3.7 P-06-7 — Prototip bağlantıları + Code Connect eşlemesi

Ne zaman kullanılır: P3 sonunda (prototip akışları) ve P5 fazında (Code Connect terfisi); kod tarafındaki karşılıklar için [07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md).

```text
Part 1 — Prototype wiring. Read the /figma-use skill before calling use_figma.
Wire the three reference screens into one flow per variant column (A..F):
Kayit Listesi -> Kayit Olustur -> Detay + Inline Edit, plus back navigation.
Motion must obey the invariants: durations 120-240ms, ease-out, functional
transitions only (state / continuity / causality).
Per-variant transition character:
- A = dissolve 120ms, color/opacity only; B = 200ms panel slide+fade;
- C = dissolve with 120ms stripe reveal; D = 200ms expand height+fade (groups);
- E = 150ms fade only; F = 200ms shadow/color, screen 240ms fade + 4px rise.
Never use scale or smart-animate effects that imply hover scaling.
Add a reduced-motion note on the flow start point: all transitions degrade to
instant or fade-only.

Part 2 — Code Connect mapping (P5, after the evaluation in file 08 promotes the
winning variant(s)).
1. Call get_code_connect_map for the file. Any component already mapped is
   DO-NOT-RECREATE; its mapping must not be overwritten — report conflicts instead.
2. For unmapped promoted components, call get_context_for_code_connect /
   get_code_connect_suggestions, then add_code_connect_map linking each Figma
   component set to its code counterpart in the claudeui repo
   (Vite + React + TypeScript + SCSS/CSS custom properties; no Next.js).
   Map the Figma `variant` property (A..F) to the code's data-variant="a".."f"
   attribute — one headless behavior layer, six CSS custom-property overlays,
   never six codebases. Map state/density/theme properties to their token-driven
   equivalents.
3. Verify with get_code_connect_map that every promoted component resolves;
   archived (non-promoted) variants keep their Figma modes but are flagged
   archived, not deleted.
```

## 4. Prompt → faz ve dosya eşlemesi

| Prompt | Faz | Girdi dosyaları | Çıktı |
|---|---|---|---|
| P-06-1 envanter+audit | Her oturum başı (P0–P5) | — | Audit raporu, DO-NOT-RECREATE listesi |
| P-06-2 Variables | P0 | [01](./01-varyant-cercevesi.md) | 4 koleksiyon: primitive, semantic (light/dark), density (3 mod), variant-overlay (6 mod) |
| P-06-3 Form set | P2 | [03](./03-form-varyantlari.md) | 12 form component set'i, variant prop A–F |
| P-06-4 Card set | P2 | [02](./02-card-varyantlari.md) | Card set (5 tür), variant prop A–F |
| P-06-5 Table set | P2 | [04](./04-table-varyantlari.md) | DataTable bölgeleri + hücre tipleri, A–F |
| P-06-5b Destekleyici set | P2 | [05](./05-bilesen-varyantlari.md) | 12 destekleyici bileşen grubu, variant prop A–F |
| P-06-6 Karşılaştırma canvas | P3 | [02](./02-card-varyantlari.md)–[05](./05-bilesen-varyantlari.md) | 3 ekran × 6 varyant × 2 genişlik × 2 tema + trade-off annotasyonları |
| P-06-7 Prototip + Code Connect | P3 / P5 | [07](./07-storybook-mcp-promptlari.md), [08](./08-degerlendirme-protokolu.md) | Flow'lar + Code Connect haritası |

## Kabul kriterleri

- [ ] Her üretim prompt'undan önce P-06-1 audit'i çalıştırıldı; audit raporu ve DO-NOT-RECREATE listesi mevcut.
- [ ] `use_figma` hiçbir çağrıda `/figma-use` skill'i okunmadan kullanılmadı.
- [ ] Scope daraltma uygulandı: tüm dosyaya `get_design_context` çağrısı yok; önce `get_metadata`, sonra node-scope'lu context.
- [ ] Dört variable koleksiyonu brief değerleriyle birebir: primitive hex'leri (#FFB900, #003399, #93A8F4, #080616, #0D0A24, #16123A, #F7F7FB, #E4E4EE, #26224A, #15803D, #DC2626, #B45309, #1D4ED8), radius 2/4/6/8 (+pill yalnız F input ve kapsül bileşenleri), space 4/8/12/16/24/32/48, density 52/44/36.
- [ ] `variant-overlay` koleksiyonu tam 6 mod (a–f, küçük harf; component property A–F büyük harf); yeni varyant/mod eklenmedi; overlay değerleri §2 tablosuyla tutarlı.
- [ ] Card, Form, DataTable aileleri ve 12 destekleyici bileşen grubu (P-06-5b) TEK component set + `variant` property A–F olarak kuruldu (6 ayrı kütüphane değil).
- [ ] Input muafiyeti varyant başına doğru: A/C/E 0px keskin, B/D 4px, F pill (padding start/end 20px); pill başka hiçbir input'ta yok.
- [ ] Hiçbir yüzey/kontrol radius'u 8px'i aşmıyor; hiçbir metin 16px altında değil; yalnız 400/500/700 weight kullanıldı.
- [ ] Sarı zemin üstü metin her yerde #080616; dark zeminde metin-seviyesi mavi her yerde #93A8F4; hardcode hex yok, yalnız variable binding.
- [ ] Her üretim adımından sonra `get_screenshot` ile fidelity kontrolü (§1.1 döngüsü) yapıldı ve sapmalar kapatıldı.
- [ ] 6-lı karşılaştırma canvas'ı: 12 frame (A–F × 320/1440) + dark kopyası + trade-off annotasyonları hazır; instance'lar detach edilmedi.
- [ ] Code Connect: mevcut eşlemeler korundu (üzerine yazılmadı), terfi eden bileşenler `data-variant` modeliyle eşlendi ve `get_code_connect_map` ile doğrulandı.
