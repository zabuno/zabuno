# AEP Design System

The design system for **EA Platform** — an AI-first, data-dense, enterprise SaaS panel suite
built for global companies. Five product modules share one interaction grammar:

| Module | Full name | Character of its screens |
|---|---|---|
| **EA** | Enterprise Architecture Platform | Analytical console: capability/application/risk grids, architecture documentation |
| **EBP** | Enterprise Business Platform | The platform shell — dashboards, cross-module navigation, session management |
| **EOP** | Enterprise Operations Platform | Exception triage, workflow queues, operational run screens |
| **EBM** | Enterprise Business Management | Reporting, governance, scorecards — reading-oriented |
| **ERX** | Enterprise Resource eXecution | Configuration, property editors, rule lists, integration surfaces |

Design language: **Flat 2.0 + contextual cards**. No neumorphism. Glass/blur is confined to the
global header and the Command Palette (always with an opaque fallback) — never on forms, tables,
menus, toasts or scrims.

## The six variants (A–F)

A–F are **not** six alternative design languages. They are six fine-grained readings of the same
language, differing **only** on 12 micro-axes, driven entirely by `data-variant="a".."f"` +
CSS custom-property overlay. Behaviour is identical across all six; any behavioural difference
is a bug.

| | Name | Separation grammar | Radius | Input | Accent dose | Natural domain |
|---|---|---|---|---|---|---|
| A | Hairline | 1px border, no tone step, no shadow | 4px | 0px sharp | Minimum — yellow only on the primary CTA | EA/EOP analytical console |
| B | Tonal | No border; surface tone steps (950→900→800) | 8px | Filled-tonal, 4px | Medium — yellow empty-state icon allowed | EBP shell / dashboard |
| C | Stripe | Faint 1px border + 2px logical-start status stripe | 6px | 0px sharp | High-signal / small area; **yellow stripe = AI provenance** | EOP exception / workflow |
| D | Inset | One grouped outer surface; inner items borderless | outer 8px, inner 0px | Borderless-inset, 4px | Low | ERX configuration |
| E | Rule | Boxes minimal; typography + horizontal rules | 2px | 0px, 2px bottom edge | Very low — CTA + critical KPI only | EBM reporting / governance |
| F | Elevated | Measured shadow (y2 b8 10%; dark: tone + 1px border) | 8px | **Pill** | Medium-high | Commerce / customer-facing |

Variant × theme × density are three independent axes: `html[data-variant]`, `html[data-theme]`,
`html[data-density]`. Locale/direction ride on `html[lang]` / `html[dir]`.

## Non-negotiable constraints

These come from the source brief and cannot be overridden by any variant, screen or component.

- **Type**: Roboto (Latin) + Noto Sans script fallback. Weights **400 / 500 / 700 only**.
  Minimum font size **1rem (16px)** everywhere — table headers, badges, captions included.
  Numeric content uses `font-variant-numeric: tabular-nums`.
- **Yellow rule**: text and icons on `#FFB900` are **always `#080616`**, never white
  (white-on-yellow is 1.72:1 — FAIL; ink-on-yellow is 11.63:1 — AAA).
- **Dark-blue ban**: `#003399` may **never** be text or a thin icon on dark surfaces (1.85:1).
  Text-level blue in dark is **`#93A8F4`** (blue/300). `#003399` in dark is only for broad fills,
  borders and structural surfaces.
- **Geometry**: radius 2/4/6/8px, operational ceiling **8px**; inputs exempt (0 / 4 / pill —
  pill only in Variant F inputs and in semantic capsules: badge, tag, avatar, switch).
  Spacing 4/8/12/16/24/32/48. Hit area **≥44×44px**.
- **Density** comfortable 52 / standard 44 / compact 36 — achieved with padding, never by
  shrinking type.
- **State is never colour alone** — icon + text, always.
- **Motion** 120–240ms ease-out, functional only. No hover scale, no hover translate.
  `prefers-reduced-motion` respected everywhere.
- **320px-first**. Bands 320/480/768/1024/1440. Logical properties throughout (RTL mirrors for
  free). Labels always visible and above the control — never a placeholder as label.
- **Icons**: Phosphor, 20–24px visible. **Emoji are forbidden.**

## Sources

Everything here was derived from the attached local folder `frontend/` — a planning-document set,
not a codebase. No production code, Figma file, font binary or logo was supplied.

| Source file | What was taken from it |
|---|---|
| `frontend/ui-variant-plan/00-genel-plan.md` | Programme plan, module list, invariant summary |
| `frontend/ui-variant-plan/01-varyant-cercevesi.md` | **Binding invariants**, the 12 micro-axes, full A–F definitions, token overlay model |
| `frontend/ui-variant-plan/02-card-varyantlari.md` | Card anatomy, 5 card types, A–F card spec |
| `frontend/ui-variant-plan/03-form-varyantlari.md` | Form inventory, 8-state set, validation model, one-column rule |
| `frontend/ui-variant-plan/04-table-varyantlari.md` | Table vs data grid, cell types & alignment, mobile strategies, perf budget |
| `frontend/ui-variant-plan/05-bilesen-varyantlari.md` | Supporting-component A–F specs (Button → CommandPalette) |
| `frontend/ui-variant-plan/09-bilesen-envanteri-ve-ajan-promptlari.md` | **The component inventory** (Layers 0–7) and the component contract |
| `frontend/AEP Design System — Grid ve Layout Sistemi….md` | Grid, breakpoints, clamp() formulas, z-index scale, layout primitives |
| `frontend/SaaS Panel Tasarım Sistemi.md`, `frontend/prompt*.md` | Product context, module definitions, paradigm rationale |

Not supplied, and therefore **absent** from this system: brand logo/wordmark, Roboto font
binaries, product screenshots, any real screen. See "Gaps and substitutions" at the end.

---

## CONTENT FUNDAMENTALS

The source material is written in Turkish for an internal audience; the product UI is
English-first with de / tr / ar as first-class locales. The voice below is what the specs
prescribe for the interface itself.

**Register — precise, operational, never chatty.** Copy names the object and the action.
"Kayıt Listesi" → *Record list*. "3 kayıt seçildi" → *3 records selected*. There is no
personality layer, no exclamation, no reassurance ("Great job!", "Oops!").

**Second person for the user, never first person for the system.** The UI addresses the user
("Review the proposed change"), and describes itself in plain declaratives ("Applied by
agent EA-Planner at 14:02"). The system never says "I".

**Errors explain *what* and *how to fix*.** The binding rule: "Geçersiz" ("Invalid") is not an
acceptable message. Every error carries three channels — a variant-specific shell mark
(border / stripe / bottom edge), a Phosphor error icon, and the message text.

- Bad: `Invalid date`
- Good: `Enter the date as DD.MM.YYYY — for example 16.08.2026`
- Bad: `Error`
- Good: `Capability code must be unique. "CAP-014" is already used by Order Orchestration.`

**Buttons are verbs, and dangerous buttons name the verb.** A confirm dialog offers **Delete**,
never **OK**. The secondary is **Cancel**. One primary action per screen.

**Status is always icon + text.** Never a bare coloured dot, never colour alone:
`✕ Failed`, `✓ Approved`, `! Needs review` (rendered as Phosphor glyphs, never emoji).

**AI copy is bounded and attributed.** The AI layer's principle — *AI diagnoses and proposes,
the human approves, the system applies* — shows up in copy as much as in colour. Proposals are
phrased as proposals ("Proposed: merge 4 duplicate capabilities"), always accompanied by a
provenance mark and a confidence level written in words plus a number ("High · 0.91"), never a
bare percentage bar.

**Casing.** Sentence case for everything — labels, buttons, headings, table headers, menu items.
No Title Case, no ALL CAPS (Turkish dotted/dotless *i* makes machine-uppercasing unsafe, and the
Intl.Collator('tr') requirement in the specs exists for the same reason). Eyebrow labels use
sentence case at 1rem/500, not small caps.

**Numbers and dates are formatted, never hand-built.** `Intl.NumberFormat`,
`Intl.DateTimeFormat`, `Intl.RelativeTimeFormat`. Absolute dates in table columns; relative time
("2 days ago") only in metadata contexts like a Timeline.

**Length discipline.** Labels ≤ 3 words. Help text one sentence. Empty states are exactly three
parts: why it is empty, what to do, one action. Copy must survive German compounding at 320px
without clipping — the fixture in the specs is a 120-character label.

**Emoji: never.** Not in UI, not in docs, not in commit messages.

---

## VISUAL FOUNDATIONS

**Colour.** A deliberately narrow palette: lemon yellow `#FFB900` as the single primary,
parliament blue `#003399` as the secondary accent, and an ink ladder that carries almost all of
the surface work — `#080616` (ink/950, the dark canvas) → `#0D0A24` (ink/900) → `#16123A`
(ink/800) on dark; `#F7F7FB` (ink/50) → `#FFFFFF` on light. Borders are `#E4E4EE` light /
`#26224A` dark. Semantic 600s: success `#15803D`, error `#DC2626`, warning `#B45309`, info
`#1D4ED8`, with brightened derivatives in dark. Yellow is *rationed*: in Variant A and E it
appears only on the primary CTA (and, in E, one critical KPI value). In Variant C, yellow is
re-purposed as a grammar — a 2px start stripe means "this content was generated by AI".

**Type.** Roboto at three weights. 400 is body and values, 500 is labels/headers/buttons,
700 is section headings and — in Variant E only — table headers and active tabs. No weight below
400 exists in the system. The type scale starts at 1rem and never goes below it; the display and
metric sizes are fluid (`clamp()`) but their floor is still readable at 320px. Numeric columns,
KPI values, badge counters and pagination all use tabular figures so digits align in a column.

**Spacing and rhythm.** 4/8/12/16/24/32/48, nothing between. Component internals use 8–16;
sections are separated by 24–48. Grid gutters come from the same scale (16 mobile, 24 desktop),
so a card's inner padding and the gap between cards belong to one rhythm. Content is capped at
1296px, forms at 720px, page margins are `clamp(16px, 5vw, 72px)`.

**Backgrounds.** Flat colour only. No gradients, no photographic hero washes, no repeating
patterns, no textures, no noise/grain. Depth is expressed by tone steps (B, D), a hairline (A),
a stripe (C), a rule (E) or a measured shadow (F) — never by imagery. The dark canvas is a
near-black violet-ink rather than neutral grey; that slight violet cast is the brand's most
recognisable surface quality. Product imagery exists only in the commerce surface (media slot of
a commerce card, hero); the specs forbid text overlaid on media, so imagery is always a discrete
block with its own fixed aspect ratio.

**Corner radii.** 2 / 4 / 6 / 8px, hard ceiling 8px for surfaces and controls. Which value gets
used is a variant decision, not a per-component decision. Inputs are the single exemption: sharp
0px in A/C/E, 4px in B/D, and full pill in F. Semantic capsules — badge, tag, avatar, switch —
may be pill in any variant. Buttons, tabs and segments are never pill.

**Cards.** A card is a *contextual* container: content is boxed only when it genuinely needs
grouping. Decorative boxing is forbidden. What a card looks like is entirely the variant's
answer to axis 1: A draws a 1px border with the surface tone identical to the canvas and no
shadow at all; B draws no border and steps the surface one tone; C draws a very faint 50%-opacity
border plus a 2px status stripe on the logical-start edge; D makes the card itself the group
(1px border + tone, inner rows borderless with 0px corners); E frequently draws no card at all,
letting a 2px rule and a 700-weight heading do the work; F is the only variant with a real
shadow — y=2 blur=8 at 10% black, and in dark mode even F swaps the shadow for a tone step plus a
1px border.

**Shadows.** Two values exist: raised `0 2px 8px rgb(0 0 0 / 10%)` and overlay
`0 4px 16px rgb(0 0 0 / 12%)`. Inner shadows do not exist. In dark mode both resolve to `none`
and separation reverts to tone + border. Only Variant F uses raised on cards; overlay is used by
overlays (modal, drawer, menu, palette) in F.

**Borders.** 1px is the working weight; 2px means something — selection, focus, a sorted column,
a section rule, a status stripe. Variant C's border token drops to 50% opacity so the stripe can
carry the signal instead.

**Focus.** `focus-visible` is mandatory on every interactive element and must never be a mere
border-colour change. Ring colour is `#003399` in light, `#93A8F4` in dark, 2px, offset 2px —
except D, which pulls the ring inside (inset, offset −2px) so it cannot spill out of a grouped
surface, and E, which adds an underline-thickening on interactive text.

**Hover.** Restrained, and never geometric. A: border darkens + 4% surface tint. B: surface
lightens one tone over 150ms. C: the stripe appears at 40% opacity + 4% tint. D: inner row picks
up one tone. E: text darkens, no tint at all (3% only on row-level items). F: z-lift — the shadow
deepens to y=4 blur=12. **No scale, no translate, in any variant.**

**Press/active.** Tone deepens one further step; no shrink transform. Selected state is separate
and persistent: a 2px blue border (A), an 8% blue fill + icon (B), a blue start stripe + 6% fill
(C), a fill + end-aligned check (D), a 2px rule or bold mark (E), permanent lift + 1px blue
border (F). Row-level items in F substitute a 1px blue border + tone fill — shadow on a table row
is forbidden.

**Motion.** 120–240ms, ease-out, functional only: state change, continuity, causality. A barely
animates (120ms colour/opacity only); B uses 150ms surface transitions and 200ms panel
slide+fade; C animates its stripe with a 120ms `scaleY`; D animates group expand/collapse over
200ms; E only fades content in at 150ms; F transitions shadow over 200ms and enters cards with
240ms fade + 4px rise. Under `prefers-reduced-motion` every one of these degrades to an instant
or a plain fade — the token durations collapse to 1ms.

**Transparency and blur.** Blur is allowed in exactly two places — the global header and the
Command Palette — and both must have an opaque fallback for `prefers-reduced-transparency` or
missing `backdrop-filter`. Scrims are flat 40% ink/950 with **no** blur. Transparency otherwise
appears only as tint percentages (3/4/6/8/10%) over a solid surface.

**Layout rules.** Sticky surfaces: header top, form footer / bulk-actions bar bottom, and only
one bottom surface at a time (the bulk-actions bar hides the bottom nav). Content scroll areas
get `scroll-padding-block` equal to the sticky heights. Panels — Option Gallery (start) and
Option Information (end) — collapse to off-canvas drawer and bottom sheet below 1024px. Tables
are the only surface permitted to scroll horizontally.

**Imagery.** Almost none by design. Where it exists (commerce), it is a plain photographic block,
neutral-to-warm, no duotone, no grain, no filter. Illustration is not part of this system; empty
states use a Phosphor glyph at 24px, not a drawing.

---

## ICONOGRAPHY

**Phosphor is the icon system**, mandated by the spec, at 20–24px visible size with a ≥44×44px
hit area. No source SVG set was supplied with the planning documents, so the official Phosphor
webfont is loaded from CDN in `tokens/fonts.css` (regular, fill and bold weights) and wrapped by
the `Icon` component. Regular is the default; fill is used for selected/active states and status
glyphs; bold is reserved for the yellow primary CTA where the ink glyph needs extra presence
against `#FFB900`. **This is a CDN dependency, not a self-hosted asset — flagged below.**

Naming follows Phosphor's own vocabulary (`check-circle`, `warning`, `x-circle`, `sparkle`,
`caret-down`, `funnel`, `dots-three-vertical`). A small semantic map is fixed by the specs:

| Meaning | Glyph |
|---|---|
| Success / approved | `check-circle` |
| Error / failed | `x-circle` |
| Warning / needs review | `warning` |
| Info | `info` |
| AI provenance | `sparkle` |
| Agent working | `circle-notch` (spinning) |
| Sort | `arrow-up` / `arrow-down` |
| Overflow menu | `dots-three-vertical` |
| Expand / collapse | `caret-right` / `caret-down` |

Decorative icons are `aria-hidden`; meaningful ones carry an accessible name. Directional glyphs
(carets, arrows) mirror under RTL via `transform: scaleX(-1)`. **Emoji are forbidden anywhere in
the product, the docs or the codebase.** Unicode characters are never used as icons.

---

## Gaps and substitutions

1. **No logo.** The sources contain no wordmark, symbol or brand file. Nothing has been drawn or
   reconstructed — wherever a mark belongs, the system renders the words **AEP** / **EA Platform**
   in Roboto 700. Supply the real mark and it drops straight into `assets/`.
2. **Fonts are not self-hosted.** The spec requires self-hosted Roboto + Noto Sans and forbids
   CDN delivery; no binaries were provided, so `tokens/fonts.css` currently `@import`s Google
   Fonts. Upload the `.woff2` files and swap the `src:` targets — no other change is needed.
3. **Phosphor is loaded from CDN** for the same reason (no icon assets in the sources).
4. **Two light-mode tones are derived**, not given: `--aep-ink-100: #EDEDF4` (the light "one tone
   down" step that Variants B and D need) and the dark-mode brightened semantic derivatives
   (`#22C55E` / `#F87171` / `#FBBF24`; info reuses `#93A8F4`). The specs demanded these
   derivatives exist but did not fix their hex values.
5. **No real product screens exist** in the sources. The UI kits recreate the three reference
   compositions the specs define (Record List, Record Create, Detail + Inline Edit) plus the
   Dashboard, Settings/Property Editor and Commerce PDP patterns — they are faithful to the
   written spec, not to any screenshot.


---

## INDEX

Root files: `styles.css` (the single stylesheet consumers link — imports only), `readme.md`,
`SKILL.md`, `thumbnail.html`.

**`tokens/`** — `fonts.css`, `colors.css`, `typography.css`, `spacing.css`, `radius.css`,
`elevation.css`, `motion.css`, `layout.css`, `density.css`, `variants.css` (the a–f overlay),
`base.css`.

**`guidelines/`** — 21 specimen cards: colour (primary, secondary, both ink ladders, semantic light
and dark, AI provenance), type (scale, weights, tabular numerals, i18n), spacing (scale, density,
hit area, radius), variants (A–F container grammar, focus per variant), brand (elevation, motion,
Phosphor map, wordmark placeholder).

### Components (69)

Set `data-variant`, `data-theme`, `data-density` on `<html>`; every component reads tokens only.

- **`components/primitives/`** — Icon, Button, IconButton, Link, Badge, Avatar, AvatarGroup,
  Divider, Spinner, ProgressBar, Skeleton, Tooltip, Kbd, VisuallyHidden
- **`components/forms/`** — FormField, TextField, TextArea, Select, Checkbox, RadioGroup, Switch,
  Slider, DateField, FileUpload, FormSection, FormFooter, ErrorSummary, InlineEdit
- **`components/data/`** — Card, Stat, DataTable, Pagination, DescriptionList, List, Timeline,
  EmptyState, TreeView, CodeBlock, BarChart, Sparkline
- **`components/navigation/`** — AppShell, GlobalHeader, SideNav, GlobalFooter, Breadcrumb, Tabs,
  SegmentedControl, Toolbar, Menu, CommandPalette, Stepper, SkipLink
- **`components/feedback/`** — Alert, Toast, Modal, ConfirmDialog, Drawer, SidePanel, Popover,
  NotificationCenter
- **`components/ai/`** — AIProvenanceBadge, PromptBar, StreamingText, ConfidenceIndicator,
  AISuggestionCard, DiffView, HumanApprovalBar, AgentStatusIndicator, AIAuditLogRow

Each has a sibling `.d.ts` (props contract) and `.prompt.md` (what it is, when to use it, example).

**Consolidations** (one component, several inventory entries — the behaviour is identical and the
source's own engineering model is "one headless layer, many looks"): TextField covers
TextField / NumberField / PasswordField / SearchField via `type`; Select covers Select / Combobox
via `searchable`; Badge covers Badge / Tag / Status via `tone` and `onRemove`; DateField covers
DateField / TimeField via `mode`; DataTable covers Table and DataGrid.

**Intentional additions** (not named in the source inventory): `AvatarGroup` (the inventory names
"Avatar / AvatarGroup" as one line), `BarChart` and `Sparkline` (the inventory says "chart
wrappers" without naming them), `VisuallyHidden` and `SkipLink` (named in the inventory).

**Not built** — named in the source inventory but deliberately left out because the sources define
no behaviour for them beyond a sentence: MultiSelect/TagInput (use Badge + Select), Calendar /
Scheduler view, JSONViewer (CodeBlock covers the JSON case), ProgressOverlay (ProgressBar + Modal),
NumberField/PasswordField/SearchField/TimeField/Combobox as separate files (see Consolidations).

### UI kits (`ui_kits/`)

| Kit | Module | Variant | Screens |
|---|---|---|---|
| `ebp-dashboard/` | EBP shell | B "Tonal", dark | Dashboard, Notifications, AI workspace |
| `ea-console/` | EA architecture | A "Hairline", light, compact | Record list, Detail + inline edit, Record create |
| `eop-exceptions/` | EOP operations | C "Stripe", dark | Exception queue, Exception detail with approval loop |
| `erx-config/` | ERX execution | D "Inset", light, comfortable | Rule property editor, Definition, Connectors |
| `ebm-report/` | EBM management | E "Rule", light | Governance report, Scorecard, Decision log |
| `commerce-pdp/` | Commerce | F "Elevated", light | Product detail with Option Gallery + Option Information |

Each kit is `index.html` + `<Kit>Screens.jsx` (+ `EbpShellFrame.jsx` for EBP) + `README.md`, and is fully
click-through.
