# 07 — Storybook MCP Prompt Kataloğu

Bu doküman, A–F varyant programının kod/test ayağını Storybook MCP üzerinden yürütecek
ajan prompt'larının bağlayıcı kataloğudur. Kapsam: token senkronu ve decorator altyapısı,
matrix story üretimi, form/table etkileşim testleri, axe fail-blocking a11y, i18n story'leri,
10k satır performans story'si ve CI kalite kapıları. Prompt'lar İngilizce ve kod bloğu
içindedir; her prompt'un üstünde ne zaman kullanılacağı Türkçe açıklanır. Tüm değerler
(hex, px, rem, weight, süre) [01-varyant-cercevesi.md](01-varyant-cercevesi.md)'deki
değişmezlerle birebir aynıdır; prompt'lar yeni token, varyant veya değer icat edemez.

Bağlantılı dosyalar: [00-genel-plan.md](00-genel-plan.md) ·
[01-varyant-cercevesi.md](01-varyant-cercevesi.md) · [02-card-varyantlari.md](02-card-varyantlari.md) ·
[03-form-varyantlari.md](03-form-varyantlari.md) · [04-table-varyantlari.md](04-table-varyantlari.md) ·
[05-bilesen-varyantlari.md](05-bilesen-varyantlari.md) · [06-figma-mcp-promptlari.md](06-figma-mcp-promptlari.md) ·
[08-degerlendirme-protokolu.md](08-degerlendirme-protokolu.md)

## 1. Kurulum ve bağlantı

Storybook MCP, Storybook 10.x dev sunucusuna `@storybook/addon-mcp` eklentisiyle eklenir ve
dev sunucusunun `/mcp` endpoint'i üzerinden (streamable HTTP) ajana açılır. Ajan bu endpoint
üzerinden story envanterini listeler, story kaynağına ve test sonuçlarına erişir; üretim
(dosya yazma) her zaman repo içinde, ajanın dosya araçlarıyla yapılır — MCP keşif ve
doğrulama kanalıdır.

| Adım | Komut / değer | Not |
|---|---|---|
| Eklenti kurulumu | `npx storybook add @storybook/addon-mcp` | Storybook 10.x, Vite builder |
| Dev sunucu | `npm run storybook` (port 6006) | Proje: `frontend/claudeui` |
| MCP endpoint | `http://localhost:6006/mcp` | Ajan `.mcp.json`'a streamable HTTP server olarak eklenir |
| Test koşucusu | `@storybook/test-runner` (Playwright tabanlı) | CI'da headless; play + axe birlikte |
| Ön koşul | Style Dictionary build çıktısı `dist/tokens/` altında | Token yoksa decorator prompt'u (Prompt 1) önce çalışır |
| MCP araçları | `get-documentation`, `get-documentation-for-story` (docs), `run-story-tests` (test), dev toolset'in değişen story/preview araçları | Araç adları kurulu addon sürümüne göre `tools/list` ile doğrulanır; prompt'lardaki atıflar bu adlara göre güncellenir |

## 2. Prompt kullanım kuralları

| Kural | Açıklama |
|---|---|
| Önce envanter, sonra üretim | Her üretim prompt'undan önce MCP'den mevcut story listesi alınır; var olan story yeniden yaratılmaz, güncellenir |
| Tek davranış katmanı | Play testleri varyanttan bağımsız aynı davranışı doğrular; varyantlar arası davranış farkı bug'dır ([01](01-varyant-cercevesi.md) mühendislik modeli) |
| Role/label sorgusu | Testler yalnız `getByRole` / `getByLabelText` kullanır; test-id ve CSS sınıf seçicisi yasak |
| Deterministik fixture | `Math.random` / `Date.now` yasak; sabit tarih 2026-08-16, seed'li PRNG |
| Token sadakati | Story ve test kodunda hardcode hex yasak; beklentiler token/attribute üzerinden kurulur |
| Türkçe/İngilizce ayrımı | Prompt'lar İngilizce; story başlıkları ve doküman prose'u Türkçe olabilir |

Prompt → faz eşlemesi ([00-genel-plan.md](00-genel-plan.md) fazları): Prompt 1 → P0–P1,
Prompt 2 → P1–P2, Prompt 3/4/5 → P2, Prompt 6 → P2 (her aile kabulünde zorunlu — 00'daki
R5 risk önlemi gereği ertelenemez; P3'te kompozisyon ekranlarına genişletilir),
Prompt 7 → P2 (table ailesi), Prompt 8 → P0'da kurulur, P5'te kalıcılaşır.

## 3. Prompt kataloğu

### 3.1 Prompt 1 — Token senkron + globalTypes decorator altyapısı

Ne zaman kullanılır: P0/P1'de, Style Dictionary çıktısı ile Storybook'un theme × density ×
variant eksenlerini tek decorator'da bağlamak ve token driftini ilk kez raporlamak için.

```text
Context: Vite + React + TypeScript + SCSS/CSS custom properties project at
frontend/claudeui; Storybook 10.x with @storybook/addon-mcp exposed at
http://localhost:6006/mcp. Design tokens live in tokens/*.json (primitive / semantic /
density / variant-overlay collections) and are compiled by Style Dictionary to CSS
custom properties.

Task:
1. Read tokens/*.json and the Style Dictionary config. Verify the build outputs:
   - dist/tokens/theme-{light,dark}.css keyed by html[data-theme],
   - dist/tokens/density-{comfortable,standard,compact}.css keyed by html[data-density],
   - dist/tokens/variant-{a,b,c,d,e,f}.css keyed by html[data-variant].
   Report every component SCSS value that bypasses var(--token) with a raw hex/px.
2. In .storybook/preview.ts define globalTypes with toolbar controls:
   - theme: light | dark (default light),
   - density: comfortable | standard | compact (default standard),
   - variant: a | b | c | d | e | f (default a).
3. Add ONE decorator that writes the three globals onto the <html> element as
   data-theme, data-density and data-variant attributes (html, not the story root div,
   so portalled overlays such as Modal/CommandPalette inherit them). No React context
   for styling, no CSS-in-JS.
4. Create a TokenSmoke story that asserts the invariants after tokens apply:
   - primary #FFB900 surfaces always pair with #080616 text, never #FFFFFF;
   - #003399 never renders as text or thin icon on #080616; text-level blue in dark
     resolves to token blue/300 = #93A8F4;
   - density changes row height via padding tokens only (52 / 44 / 36 px);
     computed font-size never drops below 1rem (16px);
   - rendered font weights are limited to 400 / 500 / 700;
   - motion durations resolve inside the 120-240ms band.
5. Output: preview.ts diff, decorator source, drift report (hardcoded values list).
Do not invent tokens, variants or values; tokens/*.json is the single source of truth.
```

### 3.2 Prompt 2 — Matrix story üretimi (görsel regresyon hedefi)

Ne zaman kullanılır: P1 kabulü (TextField 6 varyantta matrix'te) ve P2'de her bileşen
ailesine matrix story eklemek için; bu story'ler görsel regresyonun birincil hedefidir.

```text
Generate a matrix story factory for visual regression.

Task:
1. Create src/stories/matrix/createMatrixStory.tsx exporting
   createMatrixStory(Component, fixtureProps). It renders ONE CSS grid:
   rows = variants a..f, columns = theme (light, dark) x density
   (comfortable, standard, compact) -> 6 x 2 x 3 = 36 cells in a single story.
   Each cell wraps the component in a container that sets data-variant / data-theme /
   data-density locally on the cell wrapper, overriding the global html decorator.
2. Label each cell with its coordinates ("c - dark - compact") in Roboto 500 at 1rem;
   labels are intentionally part of the snapshot.
3. Generate matrix stories for: TextField, Button, Badge, Card (entity type),
   DataTable (10-row fixture). Tag them "visual". Inside matrix stories disable play
   functions and force reduced-motion emulation so snapshots are deterministic
   (variant C stripe appears instantly, variant F card enters with fade only per its
   reduced-motion rule - and even that fade is frozen for snapshots).
4. Fixtures: static strings, fixed date 2026-08-16, fixed numbers with
   font-variant-numeric: tabular-nums in numeric cells; no Math.random, no Date.now.
5. Keep DOM depth minimal, no portals inside cells; the grid must not scroll
   horizontally at 1440px.
Output: factory source + 5 matrix stories + the story ID list (tag: visual) for CI.
```

### 3.3 Prompt 3 — Form etkileşim testleri (play)

Ne zaman kullanılır: P2 form ailesi kabulünde; [03-form-varyantlari.md](03-form-varyantlari.md)'deki
validation modeli (değer korunur → açıklama → düzeltme → onay) ve ErrorSummary odak
yönetimini davranış katmanında kanıtlamak için.

```text
Write Storybook play-function interaction tests for the form family (TextField,
Select/Combobox, Checkbox/Radio/Switch, DateField, FormSection, FormFooter,
ErrorSummary) using @storybook/test (userEvent, within, expect, waitFor).

Rules:
- Query ONLY by role and accessible name/label (getByRole, getByLabelText).
  No test IDs, no CSS-class selectors: the test must prove the a11y contract.
- Parameterise each suite over variants a..f: the behaviour layer is headless and
  shared, so every scenario must pass identically in all six variants; any
  behavioural difference between variants is a bug, not a feature.

Scenarios:
1. Keyboard: Tab order follows visual order; every focused control exposes a visible
   :focus-visible indicator; Esc closes an open Combobox listbox WITHOUT clearing the
   input value; Arrow keys move through listbox options; Space toggles Checkbox and
   Switch; Radio group implements roving tabindex with arrows.
2. Validation (value-preserved flow): type an invalid value and submit; EXPECT the
   field value is preserved (never auto-cleared), aria-invalid="true" is set, and an
   error message linked via aria-describedby renders icon + text (state never colour
   alone); correct the value and resubmit; EXPECT explicit success confirmation.
3. ErrorSummary focus management: submit a form with 3 invalid fields; EXPECT focus
   moves to the ErrorSummary (heading + tabindex="-1", announced via role="alert");
   it lists exactly 3 links; activating a link moves focus into the matching field.
4. Labels: every field shows a visible label above the control (Roboto 500;
   variant D may invert to label 400 / value 500 per its property-editor semantics);
   a placeholder is never the only label; in variant C required fields carry the
   literal text "(zorunlu)", not an asterisk alone.
Verify the finished suite via the Storybook MCP test toolset (run-story-tests or the
tool name reported by tools/list) before reporting completion.
Output: play functions in the form *.stories.tsx files + shared helpers in
src/stories/testing/form.ts.
```

### 3.4 Prompt 4 — Table etkileşim testleri (play)

Ne zaman kullanılır: P2 table ailesi kabulünde; [04-table-varyantlari.md](04-table-varyantlari.md)'deki
sıralama, bulk seçim, inline edit ve klavye gezinme sözleşmelerini doğrulamak için.

```text
Write play-function interaction tests for DataTable (TanStack Table headless layer).

Scenarios:
1. Sorting: activate a sortable header by click AND by keyboard (Enter/Space);
   EXPECT aria-sort cycles none -> ascending -> descending on the columnheader;
   at most one header carries aria-sort at a time; the Phosphor caret direction
   matches; variant C renders a 2px bottom stripe on the sorted header and variant E
   thickens the bottom rule 1px -> 2px - assert via token-driven attribute/class
   state, never by raw hex.
2. Bulk select: the header checkbox selects all visible rows; EXPECT indeterminate
   state when partially selected; the bulk-actions toolbar appears and announces the
   count as text ("N rows selected"); Esc clears the selection; selection marks
   follow each variant (A: 2px blue border without fill; B: blue fill 8% light /
   #93A8F4 10% dark + icon; C: 2px blue start stripe + 6% fill).
3. Inline edit: Enter (or double-click) on an editable cell opens the editor
   pre-filled with the current value; Enter commits and returns focus to the cell;
   Esc cancels and RESTORES the previous value; invalid input follows the
   value-preserved validation model from the form suite (value kept + described
   error, icon + text).
4. Full keyboard navigation: Arrow keys move cell focus; Home/End jump to logical
   row start/end (RTL-safe); PageUp/PageDown move one visible page; Tab exits the
   grid to the next control; focus stays visible in every variant and density;
   at compact density (36px rows) interactive cell targets still expose a
   min 44x44px hit area.
Run the suite at minimum in variants a, c, f (border, stripe and elevated grammars)
and in both themes for the focus-visibility assertions. Query by role only
(grid/table, row, columnheader, gridcell/cell, checkbox).
Verify the finished suite via the Storybook MCP test toolset (run-story-tests or the
tool name reported by tools/list) before reporting completion.
Output: DataTable.interactions.stories.tsx + helpers in src/stories/testing/table.ts.
```

### 3.5 Prompt 5 — A11y fail-blocking (axe)

Ne zaman kullanılır: P2'den itibaren her aile kabulünde ve CI'da kalıcı kapı olarak;
axe ihlallerinin uyarı değil hata olması bu prompt'la kurulur.

```text
Configure @storybook/addon-a11y and the Storybook test-runner so axe violations FAIL
the run - no warnings-only mode.

Task:
1. In .storybook/preview.ts set the a11y parameter to error mode. Blocking rule set
   (never disabled): color-contrast, label, aria-required-attr, and focus rules
   (focus-order-semantics; interactive elements must have a visible focus indicator).
   Do not relax any WCAG 2.2 AA rule.
2. Generate an a11y suite per component family that runs axe against EVERY variant
   a..f in BOTH themes (reuse per-variant stories, not only the matrix grid, so the
   report attributes each violation to a variant). Contract: text contrast >= 4.5:1
   in every state (default/hover/focus/filled/error/disabled/readonly/loading).
3. Add targeted assertions axe cannot infer:
   - #FFB900 surfaces always carry #080616 text (11.63:1 AAA); #FFB900/#FFFFFF
     (1.72:1) must never occur;
   - dark theme never renders #003399 as text or thin icon on #080616 (1.85:1);
     text-level blue in dark resolves to blue/300 = #93A8F4;
   - every status conveys icon + text - query the text node, colour alone fails;
   - focus-visible in all six variants changes more than border colour alone
     (assert a computed-style delta: ring, inset ring, stripe+ring, underline
     thickening or elevation per the variant definitions).
4. Document per-variant risk checks as story annotations: B tonal ladder contrast in
   dark (950 -> 900 -> 800 separation), C 40%-opacity hover stripe is decorative
   (state also has text), E link-underline focus thickening, F dark elevation
   substitute (tone + 1px border) keeps container contrast.
Output: preview a11y config + test-runner axe hook + per-family a11y stories.
A single violation exits non-zero.
```

### 3.6 Prompt 6 — i18n story'leri (de / tr / ar-RTL)

Ne zaman kullanılır: P2'de her aile kabulünün zorunlu parçası olarak ([00](00-genel-plan.md)
R5 risk önlemi gereği ertelenemez); P3'te kompozisyon ekranlarına genişletilir. Almanca uzama,
Türkçe büyük/küçük harf ve Arapça RTL zorunlu testlerini
([01](01-varyant-cercevesi.md) i18n değişmezi) story'ye dönüştürür.

```text
Generate i18n stress stories for the form, card and table families.

Locales and fixtures:
1. de-DE: long compound words (e.g.
   "Datenschutz-Grundverordnungskonformitaetspruefung") in labels, buttons, tabs and
   table headers; EXPECT no clipped labels, wrapping allowed, containers grow
   vertically, no horizontal overflow at the 320px band.
2. tr-TR: dotted/dotless i fixtures (Istanbul with dotted capital I, "isi" family
   words) in labels and sorting; sorting and casing use Intl.Collator('tr') and
   locale-aware case mapping, never plain toUpperCase.
3. ar-EG RTL: render under html[dir="rtl"]; EXPECT a full mirror via CSS logical
   properties - table text columns align logical start, numeric/currency and actions
   align logical end, variant C status stripe sits on the logical start edge,
   Phosphor carets/chevrons flip, pinned first column pins to the logical start.
4. Intl formatting everywhere: Intl.NumberFormat, DateTimeFormat and
   RelativeTimeFormat bound to the story locale; currency/number cells keep
   font-variant-numeric: tabular-nums; fixed reference date 2026-08-16.
5. Long-label fixture: a 120-character label on TextField, Checkbox and one table
   header; EXPECT the visible label stays above the control, wraps to multiple
   lines, never degrades into a placeholder, min font-size 1rem holds and the
   interactive hit area stays >= 44x44px.
Add a "locale" globalType (en, de, tr, ar) wired to the html dir attribute and the
Intl locale. Run the ar-RTL and de cases through the matrix factory in variants a..f
so they enter visual regression.
Output: i18n fixtures module + stories tagged "i18n".
```

### 3.7 Prompt 7 — 10k satır performans story'si

Ne zaman kullanılır: P2 table ailesi ve [04](04-table-varyantlari.md) performans hedefinin
(60fps, main-thread <50ms) ölçülebilir hale getirilmesinde; sayılar [08](08-degerlendirme-protokolu.md)
skor kartına girdi olur.

```text
Create a performance story for DataTable with 10,000 rows virtualised by
TanStack Virtual.

Task:
1. Fixture: deterministic dataset from a SEEDED PRNG (fixed seed 20260816) - the
   same 10k rows on every run; columns: text, number, currency, date, status-badge,
   user, actions; numeric cells use tabular-nums.
2. The story renders the virtualised table at standard density (44px rows) inside an
   800px-high scroll container; tune overscan so no blank flash appears during
   60fps scrolling; skeleton rows share the exact real row height.
3. The play function drives measurement: programmatic scroll from row 0 to row
   10,000 in steps, then one sort toggle, one filter apply and one select-all;
   collect PerformanceObserver "longtask" entries and frame timings.
4. Budgets - exceeding any one FAILS the test:
   - scroll holds the 60fps target (no dropped-frame streak longer than 3 frames);
   - every main-thread task during sort/filter/select stays < 50ms;
   - zero mount/enter animation on virtualised rows (motion during scroll is
     forbidden in all variants, including F);
   - DOM row count stays bounded to viewport + overscan.
5. Run in variant A (densest grammar, analytical-console candidate) and variant F
   (elevated, most expensive paint) to bracket the cost range; both themes; density
   sweep comfortable/standard/compact (52/44/36) as three story permutations.
Output: DataTable.perf.stories.tsx + a perf helper that reports metrics through
story parameters so the test-runner can archive numbers per PR.
```

### 3.8 Prompt 8 — CI kalite kapıları

Ne zaman kullanılır: P0'da ilk kurulumda ve P5'te kapıların kalıcılaştırılmasında; dört
kapı (etkileşim, görsel regresyon, token drift, bundle bütçesi) PR bloklayıcıdır.

```text
Wire the Storybook quality gates into CI as PR-blocking checks.

Gates:
1. Interaction gate: @storybook/test-runner executes every play function (form,
   table, i18n, perf) against the built Storybook; any play failure or any axe
   violation (error-mode config from the a11y prompt) exits non-zero and blocks
   the merge. No skip lists without a linked issue.
2. Visual regression gate: snapshot ONLY the pruned subset below - never the full
   theoretical product of 6 variants x 2 themes x 3 densities x 5 viewport bands
   (320/480/768/1024/1440) = 180 cells per screen:
   - matrix stories (the 36-cell grid) at 1440px: one snapshot per component family
     already covers variant x theme x density internally;
   - composition screens (Kayit Listesi, Kayit Olustur, Detay + Inline Edit):
     variants a..f at 320px and 1440px, light + dark, standard density only
     -> 6 x 2 x 2 = 24 snapshots per screen;
   - i18n ar-RTL and de stories: variants a, c, f at 1440px light -> 6 snapshots;
   - the 480/768/1024 middle bands are exercised only by container-query unit
     stories of Card and DataTable (mobile strategy switch points), not full screens.
   Rationale: theme and density are token-only transforms already frozen in the
   matrix grid; layout risk concentrates at the 320/1440 extremes; a/c/f bracket
   the three separation grammars (border, stripe, elevation).
3. Token drift gate: rebuild Style Dictionary from tokens/*.json and diff the dist/
   CSS custom properties against the committed output AND against the Figma
   Variables export produced by the 06 catalogue pipeline; any unsynchronised value
   fails. Guarded set: #FFB900, #003399, #93A8F4, #080616, the ink ladder
   (#0D0A24, #16123A, #F7F7FB, #E4E4EE, #26224A), semantic 600s (#15803D, #DC2626,
   #B45309, #1D4ED8), spacing 4/8/12/16/24/32/48, radius 2/4/6/8, row heights
   52/44/36, weights 400/500/700, motion 120-240ms.
4. Bundle budget gate: size-limit on the library entry - headless core plus one
   variant overlay must stay within the agreed budget; variant overlays are
   CSS-only, so ANY variant that adds JavaScript weight fails the build; report
   per-variant CSS size in the PR so a growing overlay stays visible.
Output: CI workflow yaml + test-runner config + size-limit config + a short
CONTRIBUTING note listing the four gates and how to reproduce each locally.
```

## 4. Görsel regresyon matrisinin budanması (özet tablo)

Tam çarpım 6 varyant × 2 tema × 3 density × 5 viewport bandı = ekran başına 180 hücredir ve
sürdürülemez. (5 bant, brief'in "3 viewport" çerçevesinin [01](01-varyant-cercevesi.md)
responsive bantlarından türetilmiş bilinçli genişletmesidir — teorik tam uzayı dürüst
raporlamak için; budanmış alt küme brief'in 320/768/1440 üçlüsünü tamamen kapsar, ara
bantlar container-query unit story'leriyle doğrulanır.) Bağlayıcı pratik alt küme
Prompt 8'dekiyle aynıdır:

| Katman | Seçim | Adet | Gerekçe |
|---|---|---|---|
| Bileşen matrix'i | 36 hücreli grid, 1440px, aile başına 1 snapshot | ~5 | Varyant × tema × density zaten grid'in içinde; tek görüntü hepsini dondurur |
| Kompozisyon ekranları | a–f × 320/1440 × light/dark, yalnız standard density | 24 × 3 ekran | Layout riski uçlarda (320/1440); density token-only dönüşüm, matrix'te zaten sabit |
| i18n | ar-RTL + de, varyant a/c/f, 1440px light | 6 | a/c/f üç ayrım gramerini (border/şerit/yükselti) temsil eder; RTL mirror ve uzama en riskli i18n yüzeyleri |
| Ara bantlar (480/768/1024) | Yalnız Card + DataTable container-query unit story'leri | küçük | Ara bantlarda değişen şey kompozisyon değil, mobil strateji geçiş noktalarıdır ([04](04-table-varyantlari.md)) |

## 5. Yasaklar (bu dosyanın kapsamında)

- Story veya test kodunda hardcode hex / px — yalnız token ve attribute beklentisi.
- Test-id veya CSS sınıfı ile sorgu — yalnız role / accessible name / label.
- Deterministik olmayan fixture (`Math.random`, `Date.now`) — seed 20260816, tarih 2026-08-16.
- Axe'in uyarı moduna alınması veya WCAG 2.2 AA kuralı devre dışı bırakılması.
- Tam 180 hücrelik görsel regresyon matrisi — yalnız §4'teki gerekçeli alt küme.
- Varyantlar arasında davranış farkı üreten story/test — davranış katmanı tektir.
- Sanallaştırılmış satırlara mount animasyonu ekleyen perf story varyasyonu.
- Yeni varyant, yeni token, yeni breakpoint icat eden prompt genişletmesi.

## 6. Kabul kriterleri

- [ ] `@storybook/addon-mcp` kurulu; ajan `http://localhost:6006/mcp` endpoint'ine bağlanıp story envanterini listeleyebiliyor.
- [ ] Prompt 1 çıktısı: globalTypes (theme/density/variant) toolbar'da; tek decorator `html` üzerine `data-theme`/`data-density`/`data-variant` yazıyor; TokenSmoke story'si değişmez ihlallerinde kırmızı.
- [ ] Token drift raporu boş: component SCSS'te `var(--token)` dışı hex/px yok.
- [ ] Matrix factory 36 hücreli grid'i tek story'de üretiyor; TextField, Button, Badge, Card, DataTable matrix story'leri "visual" tag'iyle mevcut ve deterministik (reduced-motion, sabit fixture).
- [ ] Form play suite'i 6 varyantta da yeşil: klavye senaryoları, değer-korunur validation akışı, ErrorSummary odak yönetimi (3 link, odak alan içine dönüyor), görünür üst label; sorgular yalnız role/label.
- [ ] Table play suite'i a/c/f × 2 temada yeşil: `aria-sort` döngüsü tek başlıkta, bulk select indeterminate + metinli sayaç, inline edit Enter commit / Esc değer-geri-yükleme, tam klavye gezinme; compact'ta 44×44 hit area korunuyor.
- [ ] Axe error modunda: color-contrast, label, aria-required-attr ve focus kuralları bloklayıcı; her varyant × her tema için ayrı raporlanıyor; tek ihlal CI'yı kırıyor.
- [ ] Hedefli a11y assertion'ları çalışıyor: #FFB900 üstünde daima #080616; dark'ta metin-seviyesi mavi #93A8F4; durum ikon+metin; focus-visible yalnız border rengi değişiminden fazlası.
- [ ] i18n story'leri mevcut: de uzun kelime taşmasız, tr Intl.Collator sıralaması, ar `dir="rtl"` tam mirror (C şeridi logical start), 120 karakter label kuralları, tüm formatlama Intl ile.
- [ ] 10k satır perf story'si seed 20260816 ile deterministik; 60fps ve <50ms main-thread bütçeleri fail-blocking; A ve F varyantlarında, iki temada, üç density'de koşuyor.
- [ ] CI'da dört kapı PR bloklayıcı: test-runner (play+axe), budanmış görsel regresyon alt kümesi (§4), token drift (Style Dictionary ↔ commit ↔ Figma Variables export), bundle bütçesi (varyant overlay'leri CSS-only).
- [ ] Görsel regresyon seti §4 tablosuyla birebir; 180 hücrelik tam çarpım hiçbir pipeline'da yok.
- [ ] Tüm prompt'lar İngilizce ```text bloğunda, üstlerinde Türkçe kullanım açıklaması var; hiçbir prompt brief değişmezleriyle çelişen değer içermiyor.
