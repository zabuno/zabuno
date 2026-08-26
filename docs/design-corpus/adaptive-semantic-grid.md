# Adaptive Semantic Grid for a 320px-First, AI-First Enterprise SaaS Platform

## The central design decision

For the EA Platform / EBP / EOP / EBM / ERX family you described, I would not define the layout system as a conventional “mobile / tablet / desktop responsive grid.” I would define it as a **constraint-driven, semantic-priority layout system whose minimum guaranteed operating environment is 320 CSS pixels**.

I will call the proposed system **ASG-320 — Adaptive Semantic Grid 320**.

The distinction matters. A conventional responsive system starts with a desktop composition and progressively removes columns. ASG-320 starts with a harder question:

> At 320 CSS px, what information, actions, relationships and system state must remain usable without losing meaning?

Only after that contract is satisfied should extra width create additional simultaneity: side panels, secondary columns, richer tables, comparison views, AI copilots, multi-layer navigation and denser dashboards.

This is particularly appropriate for your stated audience, where 320px-class usage is not an edge case but a primary environment. It also aligns surprisingly well with WCAG: Reflow requires web content to preserve information and functionality at a width equivalent to **320 CSS pixels**, except for content whose meaning genuinely requires a two-dimensional layout. citeturn1search10turn1search3

Your existing constraints remain the design-system baseline: Roboto at a minimum weight of 400, 1rem+ typography, `#FFB900` primary, parliament blue secondary, `#080616` dark foundation, Flat 2.0/Card UI, mobile-first operation, data density, content priority, adaptive AI, i18n/g11n/l10n and a maximum 12px radius outside the defined exceptions. fileciteturn0file0

One factual clarification is useful. **320 should be treated as a CSS-layout contract, not as a hardware-resolution category.** Apple's archived Safari guidance explicitly discusses a 320px viewport and recommends `width=device-width` for device-oriented web applications. The Galaxy S4, by contrast, physically has a 1920×1080 display. Therefore, do not encode “Galaxy S4 = 320px” into the design system; create a generic **320 CSS px conformance profile** and separately maintain device/browser test profiles. citeturn5search5turn5search2

### What is actually a standard and what is your product standard

There is no W3C standard saying that mobile layouts must have four columns, tablets eight columns and desktops twelve columns. CSS standards define mechanisms such as Grid, flexible tracks, subgrid, media/container queries and flow-relative properties; **the column counts, gutters and page margins are design-system decisions**. CSS Grid is expressly a two-dimensional layout model with fixed, flexible and content-based tracks, while subgrid allows nested layouts to inherit parent grid lines. citeturn0search0turn0search1

So your system should distinguish:

| Layer | Status |
|---|---|
| 320 CSS px reflow | Accessibility standards anchor |
| Semantic HTML/source order | Accessibility/HTML architecture requirement |
| CSS Grid / Flex / Container Queries | Web-platform mechanisms |
| Logical inline/block properties | i18n/RTL architecture |
| 4 / 6 / 8 / 12 columns | ASG-320 product standard |
| 16px margin / 8px gutter at 320 | ASG-320 product standard |
| 4px spacing foundation | ASG-320 product standard |
| 48px preferred interactive row/target | ASG-320 ergonomic standard |
| AI priority slots | ASG-320 application architecture |

This separation is important because it prevents arbitrary UI fashion from being confused with engineering requirements.

## ASG-320 layout philosophy and grid specification

### The governing philosophy

ASG-320 should be based on seven rules.

**320 is the contract; everything above it is enhancement.** A feature cannot be called responsive merely because it “looks acceptable” at 320. The workflow must remain operational there.

**Information hierarchy precedes spatial hierarchy.** Every screen should know which content is P0 critical, P1 primary-task, P2 contextual and P3 supplemental before the grid decides where it goes.

**Width increases simultaneity, not functionality.** Desktop users may see the gallery, workspace and information panel simultaneously. A 320px user sees them sequentially. The underlying functionality must remain equivalent.

**Components adapt to the space they actually receive.** Viewport breakpoints should govern the application shell; reusable components should increasingly use container queries. W3C Container Queries explicitly allow a component to respond to the size of its containing element rather than assuming the size of the entire viewport. citeturn3search0

**Source order is semantic and stable.** AI or CSS may change visual emphasis, but should not produce a DOM/tab order that contradicts the logical reading sequence. Both CSS Grid and Flexbox specifications warn against using visual reordering as a substitute for correct source ordering because assistive and sequential navigation can otherwise diverge from the visual interface. citeturn0search1turn4search0

**Density must never be created by shrinking legibility.** Your 16px minimum typography stays fixed. Dense mode should remove ornamental space, reduce non-interactive padding, combine metadata and progressively disclose secondary information—not reduce text into 11–12px microcopy.

**AI is a layout participant, not the layout authority.** AI can promote, collapse, explain or contextualize regions according to defined policies, but it should not arbitrarily hide legally relevant fields, security information, destructive actions or required workflow state.

I would therefore represent the layout semantically as:

```text
P0 = critical state / blocking error / required decision
P1 = current task / primary dataset / primary action
P2 = contextual information / filters / AI recommendations
P3 = supplemental metadata / history / explanation / help
```

At 320:

```text
P0 -> always visible
P1 -> always directly reachable and normally visible
P2 -> progressively disclosed / sheet / drawer / accordion
P3 -> secondary route / details / expandable section
```

At desktop:

```text
P0 + P1 + selected P2 -> simultaneous
P3 -> contextual rail / drawer / expandable region
```

That gives Adaptive AI something deterministic to work with.

### The 320px native grid

My recommended 320px base geometry is:

```text
Viewport width     = 320px
Inline margin      = 16px each side
Usable container   = 288px
Columns            = 4
Column gutter      = 8px
Column width       = 66px
```

The formula is:

```text
columnWidth =
(viewportWidth
 - (2 × outerMargin)
 - ((columnCount - 1) × gutter))
 / columnCount
```

Therefore:

```text
(320 - 32 - 24) / 4
= 264 / 4
= 66px
```

The proportional interpretation is:

| Element | Pixels | % of 320 viewport |
|---|---:|---:|
| Start margin | 16 | 5% |
| End margin | 16 | 5% |
| Content area | 288 | 90% |
| Individual gutter | 8 | 2.5% |
| Individual column | 66 | 20.625% |

The important subtlety is that these percentages are **descriptive, not implementation values**.

Do **not** implement this as:

```css
grid-template-columns: repeat(4, 20.625%);
```

That makes the calculation brittle once gaps and nested containers enter the equation.

Implement flexible tracks:

```css
grid-template-columns: repeat(4, minmax(0, 1fr));
gap: 0.5rem;
padding-inline: 1rem;
```

CSS Grid's flexible track model is specifically intended to distribute available space across grid tracks. citeturn0search0turn0search1

### Column spans at exactly 320px

This gives you a useful design vocabulary:

| Span | Width | Viewport share | Recommended use at 320 |
|---|---:|---:|---|
| 1/4 | 66px | 20.625% | icon/stat cell only |
| 2/4 | 140px | 43.75% | small KPI/card pairs |
| 3/4 | 214px | 66.875% | unusual; secondary compositions |
| 4/4 | 288px | 90% | forms, tables, filters, cards, main content |

The practical consequence is important:

**The default mobile layout is not a four-column visual layout. It is a four-column alignment system in which most serious content spans all four columns.**

That distinction prevents the classic mistake of trying to recreate a desktop dashboard with four tiny cards side by side.

### Responsive grid tiers

For the wider system, I would use the following ASG-320 product standard:

| Available viewport | Grid | Margin | Gutter | Typical role |
|---|---:|---:|---:|---|
| 320–479 | 4 | 16px | 8px | Native phone |
| 480–767 | 6 | 24px | 12px | Plus phone / small landscape |
| 768–1023 | 8 | 32px | 16px | Mini / plus tablet |
| 1024–1439 | 12 | 32px | 16px | Laptop |
| 1440–1919 | 12 | 48px | 24px | Desktop |
| 1920+ | 12 | 48px+ | 24px | Enterprise work canvas |

These breakpoints are intentionally an ASG-320 convention rather than a claim about specific devices.

I would also not treat `1440px+` as “maximum width = 1440.” Enterprise SaaS needs two different canvas models:

**Reading Canvas** is used for forms, documentation, configuration flows and textual content. Its content width should be constrained so that fields and text do not become absurdly wide.

**Work Canvas** is used for operational tables, timelines, architecture diagrams, ERX execution surfaces, monitoring and multi-panel comparisons. It should be allowed to consume the available desktop width.

That means:

```text
Form / settings screen:
viewport 1920 -> content might remain ~800–1100px

Operational data grid:
viewport 1920 -> workspace can use ~1800px+

320px:
both -> 288px usable main column
```

### Vertical rhythm

For your typography-first requirement, I recommend a **4px foundation with a 24px reading rhythm**:

```text
space-1 = 4px
space-2 = 8px
space-3 = 12px
space-4 = 16px
space-6 = 24px
space-8 = 32px
space-12 = 48px
space-16 = 64px
```

With:

```text
Base body text = 16px / 1rem
Default body line-height = 24px / 1.5rem
Interactive control target = preferably 48px
Compact operational target = never casually below 44px
```

WCAG 2.2 AA requires pointer targets to be at least 24×24 CSS px or satisfy its spacing exceptions; the stronger AAA target-size criterion uses 44×44 CSS px. For a touch-heavy 320px-first SaaS application, using **48px as the design-system default** gives you a clean multiple of the four-pixel scale while exceeding the stronger 44px target benchmark. citeturn1search0turn1search5turn1search6

This solves an apparent contradiction in your requirements:

> “Data dense” does not need to mean “small.”

It should mean **high information value per screen area**.

## How the 320px layout should transform SaaS components

### Application shell

At 320px, I would forbid permanently visible left and right rails.

Your desktop conceptual model may be:

```text
┌──────────────┬─────────────────────────┬───────────────┐
│ Gallery      │ Main workspace          │ Information   │
│ Panel        │                         │ Panel         │
└──────────────┴─────────────────────────┴───────────────┘
```

The 320px interpretation should be:

```text
┌─────────────────────────────┐
│ Compact header              │
├─────────────────────────────┤
│                             │
│ Main task / data            │
│                             │
│                             │
├─────────────────────────────┤
│ Optional task navigation    │
└─────────────────────────────┘
```

Then:

```text
Gallery Panel
-> full-width drawer / modal sheet / dedicated view

Information Panel
-> contextual sheet / details route / full-screen inspector

AI Copilot
-> full-screen assistant or contextual sheet

Filters
-> filter sheet

Column selector
-> settings sheet

Bulk actions
-> contextual action bar
```

At larger widths those same semantic regions become persistent rails.

That is what “adaptive” should mean: **same information architecture, different simultaneous presentation**.

### Persistent chrome budget

A 320px device also has a severe vertical constraint. Apple's historical iPhone web guidance even illustrated how browser and keyboard chrome can leave a 320px-wide page with very little available vertical form space. citeturn5search8

Therefore, operational SaaS mode should have a persistent-chrome budget:

```text
Primary app bar        = ~48px
Bottom task nav        = optional ~48–56px
Secondary sticky bars  = normally 0
```

Do not simultaneously pin:

```text
brand header
+ product header
+ breadcrumb header
+ filter header
+ tab header
+ table header
+ bottom navigation
```

on a 320px viewport.

Your “multi-layer header” should be structurally multi-layered, but **not simultaneously vertically visible on mobile**.

### Forms

At 320px:

```text
Form                  -> span 4/4
Section                -> span 4/4
Label                  -> span 4/4
Input                  -> span 4/4
Help text              -> span 4/4
Validation             -> span 4/4
Primary CTA            -> span 4/4
Secondary CTA          -> span 4/4 or secondary menu
```

The default should therefore be **one semantic field per row**.

Do not compress fields merely because you technically have four grid columns.

A two-field row should be permitted only after the component's **available container width** makes it demonstrably usable. This is exactly where container queries are better than global device assumptions. citeturn3search0

For example:

```css
.form-section {
  container-type: inline-size;
}

.form-fields {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
}

@container (inline-size >= 36rem) {
  .form-fields[data-layout="pairable"] {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
```

The AI adaptation layer can decide that “First name + Last name” or two short numerical parameters are pairable, but it should not put arbitrary complex fields side by side.

### Data tables

The correct 320px strategy is **not to squeeze a desktop sheet into 288px**.

WCAG's reflow requirement explicitly makes an exception for content where a two-dimensional arrangement is necessary for use or meaning, which can include genuinely tabular structures. That does not mean every SaaS table should simply become horizontal scroll; it means you may preserve 2D interaction when preserving the relationship is essential. citeturn1search10

I would therefore give every enterprise `DataTable` three presentation modes.

**Record mode — default at 320.**

```text
Order #92831
Customer: ABC GmbH
Status: Pending
Amount: €18,450

[View] [Actions]
```

Each source table row becomes a semantically structured record card/list row.

**Matrix mode — for genuinely compact data.**

Use when there are perhaps two or three concise comparison columns that fit meaningfully.

**Sheet mode — explicit two-dimensional mode.**

Use for ERP/ERX-like spreadsheet workflows where column relationships must remain visible. The container may scroll horizontally while the page itself continues to reflow. Primary identifiers can remain sticky.

Accessible HTML table structure should still communicate the relationship between header and data cells; W3C's table guidance emphasizes explicit `<th>` and `<td>` relationships and, for more complex structures, appropriate `scope`, `headers` and associations. citeturn3search2

So the mobile data-table rule becomes:

```text
Never remove data merely to make columns fit.

First:
transform presentation.

Second:
progressively disclose low-priority fields.

Third:
offer true sheet mode when 2D comparison is essential.
```

### Cards and Bento layouts

Bento is useful at 320 only as **priority-driven stacking**, not as desktop-style geometric mosaics.

Desktop:

```text
┌─────────────────┬──────────┐
│ Revenue         │ Risk     │
│ 6 cols          │ 3 cols   │
│                 ├──────────┤
│                 │ Alerts   │
├────────┬────────┴──────────┤
│ Orders │ AI Recommendation │
└────────┴───────────────────┘
```

320:

```text
Revenue
↓
Risk
↓
Alerts
↓
Orders
↓
AI recommendation
```

AI may alter ordering **within explicitly allowed priority groups**, but the semantic source order should remain sensible for keyboard and assistive navigation. CSS Grid itself warns that visual reordering should not replace correct document ordering. citeturn0search1

### Gallery and information panels

At 320:

```text
Main workspace = 4 columns

Option Gallery =
full-width modal/drawer

Option Information =
full-width contextual inspector

Never:
gallery 60px
+ workspace 160px
+ info 84px
```

That latter pattern preserves the desktop shape at the expense of usability.

At tablet:

```text
Gallery       = 2–3 columns
Main          = remainder
Information   = overlay by default
```

At laptop:

```text
Gallery       = 2–3 / 12
Main          = 6–8 / 12
Information   = 3–4 / 12
```

At large desktop:

```text
Gallery       = contextual fixed/minmax track
Main          = flexible 1fr
Information   = contextual fixed/minmax track
```

The desktop application shell may therefore be better expressed with **semantic tracks** than with simple percentage widths:

```css
grid-template-columns:
  minmax(15rem, 18rem)
  minmax(0, 1fr)
  minmax(18rem, 24rem);
```

while the inner workspace still conforms to the twelve-column design grid.

### Header and footer

For your Alibaba/Amazon-scale multi-level structures, separate two contexts.

The **commerce/public shell** may legitimately have a complex category header and extensive footer.

The **enterprise operational shell** should not reproduce a marketplace mega-header on every operational page.

At 320, a multi-layer header should collapse into:

```text
48px app bar
brand / module identity
global search or command entry
primary menu
context action
```

Everything else becomes navigation state.

A complex footer becomes accordions:

```text
Company
Products
Resources
Legal
Locale
```

rather than dozens of always-visible links.

## AI-first, fluid and global layout architecture

### AI should work through slots

For 2030/2035 readiness, do not let an LLM directly “design the page.”

Define an explicit slot contract:

```ts
type SemanticPriority = 'P0' | 'P1' | 'P2' | 'P3';

interface AdaptiveRegion {
  id: string;
  priority: SemanticPriority;
  required: boolean;
  collapsible: boolean;
  movable: boolean;
  mobilePresentation:
    | 'inline'
    | 'drawer'
    | 'sheet'
    | 'route'
    | 'hidden-only-by-user';
  desktopPresentation:
    | 'main'
    | 'rail-start'
    | 'rail-end'
    | 'overlay';
}
```

Then AI may recommend:

```text
Promote Risk Alert from P2 -> P0
Open supplier context panel
Collapse noncritical history
Pre-fill shipping filter
Show predicted anomaly
```

but cannot decide:

```text
Hide security warning
Move destructive action above confirmation
Remove required tax field
Change reading order unpredictably
```

This gives you **Adaptive AI without adaptive chaos**.

### Container-first components

The long-term system should have two responsive levels:

```text
Viewport responsiveness
    controls application shell

Container responsiveness
    controls component morphology
```

For example:

```text
Viewport 1440px
but table placed inside 360px side panel
-> table uses narrow presentation

Viewport 768px
but hero has 704px available
-> hero may use wider presentation
```

That is exactly the problem container queries were designed to solve: components can query their own containing block instead of inferring their environment from the global viewport. citeturn3search0

### Use logical rather than physical coordinates

For complete LTR/RTL layout architecture, tokens and components should use:

```css
padding-inline
margin-inline
inset-inline-start
inset-inline-end
border-inline-start
inline-size
block-size
text-align: start
```

rather than hardcoding:

```css
padding-left
margin-right
left
right
text-align: left
```

CSS Logical Properties exist precisely to map spacing and dimensions relative to writing mode and direction instead of physical left/right coordinates. citeturn4search1turn3search3

Your grid therefore becomes:

```text
start rail -> main -> end rail
```

not:

```text
left sidebar -> main -> right sidebar
```

That is a much stronger foundation for Arabic, Hebrew and future bidirectional content.

### Dynamic viewport sizing

For full-height panels, avoid building your future SaaS shell exclusively around legacy `100vh`.

CSS Values Level 4 distinguishes small, large and dynamic viewport units. `svh` represents the viewport with dynamic browser UI visible, while `dvh` tracks the dynamically changing viewport. This matters for full-screen mobile inspectors, AI sheets and forms whose available height changes with browser UI. citeturn3search1

A modern shell can therefore use patterns such as:

```css
.app-shell {
  min-block-size: 100svh;
}

.fullscreen-sheet {
  block-size: 100dvh;
}
```

with appropriate fallbacks according to the browsers you support.

### The 2026–2035 trajectory

For **2026–2027**, I would make the architecture concrete and conservative:

```text
CSS Grid
Subgrid where useful
Flexbox for one-dimensional clusters
Container queries
Logical properties
320 CSS px conformance
Storybook MCP
Automated interaction + accessibility stories
Stable design tokens
Semantic AI slots
```

CSS Grid is explicitly optimized for two-dimensional alignment, while Flexbox is a complementary one-axis layout model; mixing the two according to their strengths is the correct architectural approach. citeturn0search1turn4search0

The **2030 vision** should be:

> components no longer care what named “device breakpoint” they are on; they know their available container, information priority, user density preference, writing direction and interaction modality.

The **2035 vision** should go further:

> layout becomes semantic and policy-based rather than coordinate-based.

The code should increasingly describe:

```text
What is this information?
How important is it?
What relationships must remain visible?
Can it be collapsed?
Can it move?
Does it require 2D representation?
What is the user's current task?
```

rather than:

```text
At 768px make this width 50%.
At 1024px move this to the right.
```

That is a vision, not a prediction about a future CSS specification. But the current Grid, container-query and logical-property architecture allows you to move toward it without redesigning the application from scratch. citeturn0search0turn3search0turn4search1

## Storybook MCP prompts for building the layout system

There is an important 2026 update compared with the earlier MCP ecosystem. Storybook now has an **official MCP server**. Storybook MCP became available for React in Storybook 10.3 in March/April 2026, and the current documentation surfaced during this research is Storybook 10.5. The official documentation still labels AI manifests/MCP capabilities as preview and currently states that they are React-only, with APIs subject to change. citeturn2search6turn2search7turn2search1

The official MCP workflow exposes component/documentation knowledge and development/testing operations to agents. Storybook explicitly recommends querying its documentation rather than hallucinating component APIs and provides tools such as `list-all-documentation`, `get-documentation`, `get-storybook-story-instructions`, `preview-stories`, `get-changed-stories` and test operations such as `run-story-tests`. citeturn2search1turn2search2

The following prompts are therefore written for the current official Storybook MCP model rather than third-party MCP implementations.

### Master prompt — create the entire ASG-320 layout foundation

Copy this into your coding agent connected to Storybook MCP:

```text
You are the principal frontend architect and design-system engineer for a
global Enterprise SaaS design system.

The product family includes:

- EA Platform — Enterprise Architecture Platform
- EBP — Enterprise Business Platform
- EOP — Enterprise Operations Platform
- EBM — Enterprise Business Management
- ERX — Enterprise Resource eXecution

This task is NOT primarily about UI components.
This task is about establishing the application's complete LAYOUT SYSTEM.

Before implementing anything, use the connected Storybook MCP server.

MANDATORY MCP WORKFLOW

1. Call `list-all-documentation`.
2. Inspect all existing layout, shell, grid, Stack, Cluster, Container,
   Card, Form, DataTable, Drawer, Sheet, Header, Footer, Navigation,
   GalleryPanel and InformationPanel documentation.
3. Call `get-documentation` for every relevant existing primitive.
4. Never invent props that are not documented.
5. Call `get-storybook-story-instructions` before writing stories.
6. Reuse existing design-system primitives wherever possible.
7. After implementation, call `preview-stories`.
8. Run `run-story-tests`.
9. Fix accessibility, interaction, overflow and responsive failures.
10. Repeat tests until the relevant stories pass.

PROJECT PHILOSOPHY

Create a layout architecture called:

ASG-320 — Adaptive Semantic Grid 320.

The fundamental contract is:

320 CSS pixels first.

320px is not merely the smallest breakpoint.
It is the minimum fully operational product state.

Larger widths must progressively increase simultaneity and information
density but must not introduce functionality unavailable at 320px.

Do not start by designing desktop and collapsing it.

Start at exactly:

width: 320 CSS px.

The 320px state must preserve:

- information
- workflow state
- primary actions
- data relationships
- accessibility
- localization
- validation
- AI context
- navigation
- error recovery

DESIGN CONSTANTS

Typography:
- Roboto primary typeface
- minimum weight 400
- minimum body font size 1rem / 16px
- never create 11px, 12px, 13px, 14px microcopy to solve density

Colors:
- primary: #FFB900
- secondary accent: parliament blue
- dark application background: #080616 or darker compatible token
- preserve existing light theme tokens

Radius:
- maximum general radius: 12px
- input-field radius may follow the existing form-system exception
- do not introduce excessive pill UI

Visual philosophy:
- Content First
- Typography First
- Mobile First
- AI First
- Data Dense
- Conversion First
- Motion First
- Flat 2.0
- Card UI
- high-information enterprise aesthetic
- avoid decorative glassmorphism inside forms and data tables

ACCESSIBILITY

Treat 320 CSS px reflow as a hard acceptance test.

Interactive controls should normally provide a 48px interaction region.
Never reduce visible typography below 16px to make content fit.

Preserve logical DOM/source order.

CSS visual rearrangement must not create an illogical keyboard,
screen-reader or reading sequence.

Use semantic HTML.

Use CSS logical properties rather than hardcoded left/right layout
where possible.

GLOBALIZATION

Architecture must support:

- g11n
- i18n
- l10n
- LTR
- RTL
- longer translated strings
- locale-sensitive numbers
- locale-sensitive currency
- locale-sensitive dates
- mixed-direction text

Use logical concepts:

inline-start
inline-end
block-start
block-end

Do not design a fundamentally "left-sidebar" architecture.
Design:

start rail
main workspace
end rail

GRID FOUNDATION

At 320px:

viewport = 320px
outer inline margin = 16px
usable content = 288px
columns = 4
column gap = 8px
column width at exactly 320px = 66px

Use CSS Grid flexible tracks.

Preferred implementation:

grid-template-columns:
  repeat(4, minmax(0, 1fr));

gap:
  var(--grid-gap);

padding-inline:
  var(--layout-margin);

Do NOT implement the grid by manually assigning percentage widths
to every column.

Create the following responsive tiers:

320–479:
4 columns
16px margin
8px gap

480–767:
6 columns
24px margin
12px gap

768–1023:
8 columns
32px margin
16px gap

1024–1439:
12 columns
32px margin
16px gap

1440+:
12 columns
48px margin
24px gap

Treat these as design-system tokens rather than scattered media-query
magic numbers.

Create CSS variables/tokens similar to:

--layout-columns
--layout-margin
--layout-gap
--layout-max-reading
--layout-max-form
--layout-work-canvas
--layout-rail-start
--layout-rail-end

SPACING

Use a 4px foundation:

4
8
12
16
24
32
48
64

Default body typography:
16px / 24px.

Preferred interactive height:
48px.

LAYOUT ARCHITECTURE

Create reusable layout primitives rather than page-specific CSS.

At minimum investigate/build:

Page
AppShell
WorkCanvas
ReadingCanvas
Grid
Subgrid
Stack
Cluster
Split
Sidebar
Rail
Region
Section
StickyRegion
ScrollRegion
ResponsivePanel
AdaptiveDrawer
AdaptiveSheet
AdaptiveInspector
AdaptiveNavigation
FormLayout
DataLayout
DashboardLayout

Do not duplicate spacing/grid logic inside application components.

VIEWPORT VS CONTAINER RESPONSIVENESS

Use viewport/media queries only for major application-shell transitions.

Use CSS container queries for reusable component morphology.

Example principle:

A DataTable in a 360px panel must use narrow behavior even when
the viewport is 1440px.

A Card should react to its own available width.

FORMS

320px default:
all serious form fields span 4/4 columns.

Do not create two-column forms at 320.

Labels remain outside fields.
Help and validation messages must remain visible without overlap.

Only create paired fields when their containing layout has enough width.

Use container-query-driven form compositions.

TABLES

Do NOT compress a 12-column desktop table into 288px.

Implement three table presentation contracts:

1. record mode
2. matrix mode
3. sheet mode

Record mode:
default narrow/mobile representation.

Matrix mode:
for genuinely small comparison structures.

Sheet mode:
for true two-dimensional datasets requiring horizontal navigation.

Preserve semantic table markup where data relationships are tabular.

Do not delete critical data merely to make a table appear responsive.

APPLICATION SHELL

At 320px:
do not keep permanent start and end rails.

Main workspace:
100% usable content width.

Start/Gallery panel:
drawer, sheet or dedicated view.

End/Information panel:
contextual inspector, sheet or dedicated view.

AI copilot:
contextual sheet/full-screen mode.

At desktop:
these can progressively become persistent rails.

HEADER

The system may support a highly sophisticated multi-layer header,
but 320px must not show all header layers simultaneously.

Operational mobile mode should expose only the essential application
bar and current context.

Secondary layers become menus, drawers, sheets or navigation routes.

FOOTER

Public/commerce footer:
responsive accordion structure at narrow widths.

Operational SaaS workspace:
avoid consuming large vertical space with a marketplace-style footer.

AI-FIRST SEMANTIC PRIORITY MODEL

Create a formal region model:

P0 = critical/blocking
P1 = primary task
P2 = contextual
P3 = supplemental

Each adaptive region must declare:

- semantic priority
- required/not-required
- collapsible
- movable
- mobile presentation
- desktop presentation
- AI adaptation permission

AI MAY:
- promote urgent information
- collapse secondary information
- open contextual information
- recommend layout density
- surface relevant controls

AI MUST NOT:
- hide required input
- hide security warnings
- hide compliance information
- relocate destructive actions unpredictably
- break reading/tab order
- modify layout continuously while the user is interacting

LAYOUT STABILITY

Reserve predictable space for AI-generated recommendations.

Do not cause major layout jumps when asynchronous AI content appears.

MOTION

Motion must explain state and spatial relationships.

Use motion for:
- panel transitions
- drill-down
- expansion
- contextual relationship
- state change

Do not use motion merely as decoration.

Respect prefers-reduced-motion.

DELIVERABLES — DOCUMENTATION FIRST

Before implementing layouts create:

docs/layout/README.md
docs/layout/layout-philosophy.md
docs/layout/grid-system.md
docs/layout/grid-tokens.md
docs/layout/320-native-contract.md
docs/layout/responsive-tiers.md
docs/layout/container-query-strategy.md
docs/layout/app-shell.md
docs/layout/form-layout.md
docs/layout/data-table-layout.md
docs/layout/panel-layout.md
docs/layout/ai-adaptation-policy.md
docs/layout/i18n-rtl-layout.md
docs/layout/accessibility-contract.md
docs/layout/testing-matrix.md

Each document must explain:
- purpose
- rule
- reason
- allowed behavior
- forbidden behavior
- examples
- Storybook stories that prove the rule

STORYBOOK STRUCTURE

Create layout stories such as:

Foundation/Layout/Grid/320
Foundation/Layout/Grid/480
Foundation/Layout/Grid/768
Foundation/Layout/Grid/1024
Foundation/Layout/Grid/1440

Foundation/Layout/AppShell/Native320
Foundation/Layout/AppShell/PlusPhone
Foundation/Layout/AppShell/Tablet
Foundation/Layout/AppShell/Laptop
Foundation/Layout/AppShell/Desktop

Patterns/FormLayout/Native320
Patterns/FormLayout/Wide

Patterns/DataLayout/Record320
Patterns/DataLayout/Matrix320
Patterns/DataLayout/Sheet320
Patterns/DataLayout/Desktop

Patterns/Panels/Gallery320
Patterns/Panels/Information320
Patterns/Panels/DesktopThreePane

Patterns/Internationalization/LTR
Patterns/Internationalization/RTL
Patterns/Internationalization/LongGerman
Patterns/Internationalization/MixedDirection

Patterns/AI/RecommendationInline
Patterns/AI/ContextSheet320
Patterns/AI/DesktopRail
Patterns/AI/LoadingReservedSpace

For every important story test:

320px
360px
480px
768px
1024px
1440px

Also test:
dark
light
LTR
RTL
long translation strings
200% text zoom where applicable
keyboard navigation
reduced motion
loading
empty state
error state
large dataset
AI content loading

STRICT ACCEPTANCE CRITERIA

At exactly 320 CSS px:

- no page-level horizontal overflow
- no clipped required content
- no unreadable labels
- no text below 16px
- no overlapping controls
- no loss of primary functionality
- no permanent sidebars
- no desktop table compressed into unusability
- no visually reordered but semantically incorrect DOM
- no reliance on hover
- no invisible validation state
- no AI content covering primary actions

Exceptions to horizontal overflow are isolated two-dimensional work
surfaces such as explicit sheet/table regions when preserving the
two-dimensional relationship is essential.

The page itself must remain usable and reflow correctly.

TEST AND SELF-CORRECT

When implementation is complete:

1. preview all important stories
2. run Storybook story tests
3. inspect accessibility failures
4. inspect 320px overflow
5. inspect keyboard order
6. inspect RTL
7. inspect long content
8. inspect loading/layout shift
9. fix issues
10. rerun tests

Do not declare completion merely because the desktop screenshots look good.

The 320px stories are the primary acceptance baseline.

Finally generate:

docs/layout/implementation-report.md

Include:
- architecture created
- design decisions
- known tradeoffs
- components reused
- components created
- exceptions
- test results
- remaining risks
```

This prompt follows Storybook's current MCP guidance: agents should inspect documented component APIs, use current story instructions, preview work and validate it with focused tests rather than inventing component contracts. citeturn2search1turn2search10

### Specialized prompt — generate the grid tokens and layout primitives

```text
Using Storybook MCP, audit the existing design system before modifying code.

Call:
- list-all-documentation
- get-documentation for existing layout primitives
- get-storybook-story-instructions

Then implement the ASG-320 grid foundation.

Create a single source of truth for responsive layout tokens.

Required viewport contract:

320–479:
4 columns
16px inline margins
8px grid gap

480–767:
6 columns
24px inline margins
12px grid gap

768–1023:
8 columns
32px inline margins
16px grid gap

1024–1439:
12 columns
32px inline margins
16px grid gap

1440+:
12 columns
48px inline margins
24px grid gap

At exactly 320px:
usable content width = 288px
4 columns
3 gutters
column width = 66px

Create:

LayoutContainer
LayoutGrid
LayoutItem
Stack
Cluster
Split
WorkCanvas
ReadingCanvas

Use:
CSS Grid for 2D layouts.
Flexbox for one-dimensional alignment.
Container queries for component-local responsive behavior.
Logical CSS properties for RTL/LTR.

Avoid:
arbitrary percentage-based columns,
duplicated breakpoint values,
hardcoded margin-left/right,
negative-margin layout hacks,
JS-driven layout where CSS is sufficient.

Create visual Storybook grid-debug stories.

For every viewport display:
- current width
- active column count
- margin
- gutter
- calculated track size
- span overlays

Provide stories at:
320
360
480
768
1024
1440
1920

At 320 render examples of:
span 1
span 2
span 3
span 4

Run story tests and correct overflow/accessibility failures.
```

CSS Grid and Flexbox are complementary rather than competing choices: Grid provides two-dimensional alignment; Flexbox provides one-axis distribution and alignment. citeturn0search1turn4search0

### Specialized prompt — native 320px SaaS application shell

```text
Build a Native320AppShell using the existing Storybook components.

Use Storybook MCP documentation before using any component.

The shell represents an EA / EBP / EOP / EBM / ERX operational product.

At exactly 320px:

- viewport is the primary design target
- inline margin: 16px
- main grid: 4 columns
- grid gap: 8px
- body typography: >=16px
- preferred primary interaction height: 48px

Do not show permanent sidebars.

Architecture:

AppBar
MainWorkspace
ContextActions
optional BottomTaskNavigation

GalleryPanel:
closed by default
opens as full-width drawer/sheet

InformationPanel:
opens as contextual full-width inspector

AI Copilot:
opens as dedicated sheet/full-screen region

Filters:
sheet/drawer

Notifications:
overlay or dedicated page

Account/navigation:
menu/drawer

Do not stack several sticky header rows.

Create an explicit persistent-chrome budget.

When width increases:

480:
allow more horizontal action grouping.

768:
allow optional start rail.

1024:
allow two-pane workspace.

1440:
allow three-pane:
start gallery + main workspace + end inspector.

Use the same semantic DOM regions across all states.

Do not solve desktop layout by changing logical source order.

Create Storybook stories:

Native320
Native320GalleryOpen
Native320InformationOpen
Native320AIActive
Native320KeyboardOpenSimulation
Tablet
Laptop
DesktopThreePane
RTL320
LongTranslation320
Dark320
Light320

Verify:
no 320 page overflow
logical keyboard order
focus visibility
RTL mirroring
drawer dismissal
reduced motion
no content hidden underneath fixed chrome.

Run Storybook tests and fix failures.
```

### Specialized prompt — 320px-first form layout

```text
Create the enterprise SaaS FormLayout system.

Do not redesign individual Input components unless required.
Focus on layout composition.

320px is the primary state.

At 320:
- every normal field spans 4/4 columns
- one semantic field per row
- label above field
- help text below
- error/validation below
- no side-by-side complex fields
- primary submit action clearly reachable
- destructive action separated from primary submit

Minimum typography:
16px.

Do not reduce typography to increase density.

Create semantic field-group metadata:

pairable
full-width
critical
optional
advanced
AI-assisted

Only `pairable` groups may become two-column layouts when their
container is wide enough.

Use container queries rather than only viewport breakpoints.

Adaptive AI may:
- recommend values
- provide inline explanations
- surface dependent fields
- identify likely errors

Adaptive AI may not:
- remove required fields
- silently alter submitted values
- move destructive actions unpredictably
- cover validation text

Reserve space for asynchronous AI suggestions to minimize layout jumps.

Create stories:

Form320Default
Form320Validation
Form320LongLabels
Form320AIRecommendations
Form320Keyboard
Form320RTL
Form480
Form768Paired
FormDesktop
FormDense
FormComfortable
Dark
Light

Test:
keyboard sequence
label/input association
validation
error summary
translated labels
RTL
320px overflow
touch target size
reduced motion.

Use Storybook MCP to preview and run story tests.
```

### Specialized prompt — responsive enterprise DataTable

```text
Create an enterprise DataTable layout architecture whose primary
operating environment is 320 CSS pixels.

Do NOT solve mobile by shrinking desktop columns.

Implement three explicit presentation modes.

MODE A — RECORD

Primary narrow-screen mode.

Transform each table row into a structured record view while preserving
the same underlying data model.

Display:
primary identifier
primary status
primary numeric/business value
one or two essential supporting attributes
actions

Secondary fields remain reachable through disclosure/details.

MODE B — MATRIX

Use only where the dataset has a genuinely small number of concise
columns that remain meaningful at narrow width.

MODE C — SHEET

Preserve a true 2-dimensional grid for ERP/ERX/spreadsheet workflows
where row/column comparison is essential.

The sheet container may horizontally scroll.

Do not cause the entire page to horizontally scroll.

Keep primary row identifiers available where practical.

Preserve semantic table markup whenever the data is semantically a table.

Do not hide critical data purely to make the UI visually fit.

Create responsive policy metadata:

column priority:
P0
P1
P2
P3

presentation:
always
summary
details
sheet-only

AI may recommend a view based on task context,
but users must be able to explicitly select available presentation modes.

At exactly 320px:
- page width must remain stable
- Record mode must be fully usable
- Sheet mode overflow must be isolated inside the table workspace
- typography >=16px
- controls must remain touch operable

Create stories:

Record320
Record320Dense
Record320LongValues
Record320BulkSelection
Matrix320
Sheet320
Sheet320Scrolled
RecordRTL320
SheetRTL320
Tablet
Laptop
DesktopWide
HundredRows
ThousandsOfRowsVirtualized
Loading
Empty
Error
AIHighlightedRows

Run accessibility and interaction story tests.

Do not mark the work complete until the 320 stories are usable.
```

W3C's reflow criterion permits exceptions for content that inherently needs two-dimensional layout, while its table guidance requires the actual data/header relationships to remain semantically encoded. citeturn1search10turn3search2

### Specialized prompt — option gallery and information inspector

```text
Design a responsive two-context panel system:

Start context:
OptionGalleryPanel

End context:
OptionInformationPanel

The conceptual desktop architecture is:

Gallery | Workspace | Information

But 320px MUST NOT display three narrow columns.

At 320:

Workspace:
full available width

Gallery:
full-width modal drawer or dedicated view

Information:
full-width contextual inspector or dedicated view

Never show both contextual panels persistently at 320.

Opening a context panel must preserve:
- originating selection
- scroll position
- workflow state
- unsaved changes
- keyboard focus restoration

At medium widths:
support Gallery + Workspace OR Workspace + Information.

At large widths:
allow all three.

Use semantic track naming:

start-context
main
end-context

Do not hardcode left/right terminology.

Support RTL automatically through logical layout.

Create states:

320Default
320Gallery
320Information
320GallerySearch
320InformationEdit
320UnsavedChanges
320AIContext
768GalleryWorkspace
1024WorkspaceInformation
1440ThreePane
RTL320
RTL1440

Motion:
explain where the contextual panel came from.

Respect prefers-reduced-motion.

Run Storybook tests.
```

### Specialized prompt — AI-first layout policy

```text
Implement the AdaptiveLayoutPolicy layer for ASG-320.

This is a policy system, not a generative visual-design system.

Create a typed semantic layout contract.

Every adaptive region must define:

id
semanticPriority: P0 | P1 | P2 | P3
required
collapsible
movable
aiPromotable
aiCollapsible
mobilePresentation
desktopPresentation

Rules:

P0:
never automatically hidden.

P1:
directly accessible and normally visible.

P2:
may move to contextual sheets/rails.

P3:
may use progressive disclosure.

AI is allowed to:
promote urgency
open context
collapse optional sections
recommend density
highlight relevant regions

AI is prohibited from:
changing semantic DOM order
hiding required controls
hiding security/compliance warnings
hiding blocking validation
moving destructive controls unpredictably
continually rearranging UI during interaction

Adaptive state changes must be explainable and reversible.

The user must have a way to return to a stable/default layout.

Create Storybook simulations:

320NoAI
320AIRecommendation
320CriticalAlert
320AIInspector
320UserRejectsAdaptation
DesktopAIRail
AIUnavailable
AISlowResponse
AIError
ReducedMotion

Reserve stable layout space where AI content appears.

Test that delayed AI responses do not cover or displace primary actions.
```

### Specialized prompt — globalization and RTL grid validation

```text
Audit ASG-320 for complete directional and localization resilience.

Use Storybook MCP documentation first.

Eliminate avoidable physical layout assumptions.

Prefer:

padding-inline
margin-inline
inset-inline-start
inset-inline-end
border-inline-start
inline-size
max-inline-size
text-align: start

Audit for:
left:
right:
margin-left:
margin-right:
padding-left:
padding-right:

Physical properties may remain only where physical direction is
intentionally visual rather than writing-direction dependent.

Test:

English LTR
Turkish LTR
German long strings
Arabic RTL
Hebrew RTL
mixed Arabic + Latin IDs
long currency values
large numbers
localized dates
translated navigation labels

For each scenario render:

320
480
768
1024
1440

Grid itself must mirror logically where appropriate.

Source order must remain semantically correct.

Do not create separate RTL component implementations unless absolutely
necessary.

Create an audit report documenting any non-logical directional CSS.
```

CSS Logical Properties and Writing Modes are the correct platform-level foundation for this because they express sizing and edges relative to block/inline flow rather than assuming every language starts at the physical left edge. citeturn4search1turn3search3

### Specialized prompt — Storybook MCP acceptance and self-healing loop

```text
Act as the QA gatekeeper for the ASG-320 layout system.

Use Storybook MCP.

First:
call get-changed-stories.

Then identify every story affected by layout-system changes.

Preview the relevant stories.

Run story tests.

Prioritize exactly 320px.

Evaluate each story against the following contract:

REFLOW
- no page-level two-axis scrolling
- no clipped required content
- no content rendered outside usable viewport
- intentional sheet/table scroll containers are isolated

TYPOGRAPHY
- normal text >=16px
- Roboto >=400 where supported by the design system
- no density workaround using micro-fonts

INTERACTION
- primary touch targets preferably >=48px
- keyboard focus visible
- keyboard order matches logical reading order
- no hover-only functionality

LAYOUT
- 16px margins at 320
- 4-column underlying grid
- 8px grid gap
- main content can span full 4 columns
- no permanent dual sidebars
- no desktop table artificially squeezed into 288px

AI
- asynchronous content does not cover actions
- AI cannot hide P0/P1 required state
- layout adaptation is reversible

I18N
- RTL usable
- long translated text wraps correctly
- numbers/currency do not destroy layout

MOTION
- reduced motion respected

THEMES
- dark/light behavior remains layout-equivalent

Test at minimum:

320x480
320x568
360x640
480x800
768x1024
1024x768
1440x900
1920x1080

For each failure:

1. determine root cause
2. fix the underlying layout primitive where possible
3. do not add page-specific hacks if the problem is systemic
4. rerun affected stories
5. repeat until passing

Generate:

docs/layout/layout-conformance-report.md

Include a matrix:

story
320
360
480
768
1024
1440
RTL
dark
light
keyboard
a11y
status

Do not call the layout system production-ready if the desktop stories
pass but Native320 fails.
```

Storybook's official MCP design is explicitly intended to support this kind of loop: the agent can inspect real component/documentation metadata, generate or modify stories, preview them, run component/accessibility tests and then repair failures. citeturn2search1turn2search6

## The design rules I would make non-negotiable

The most important conclusion of the research is that **your layout philosophy should not be “responsive desktop SaaS.” It should be “native 320 semantic SaaS with progressively richer simultaneous presentation.”**

For your platform, I would put the following rules into the design-system governance document.

| Rule | ASG-320 decision |
|---|---|
| Minimum product width | 320 CSS px |
| 320 page margin | 16px / 5% of viewport |
| 320 content width | 288px / 90% |
| 320 grid | 4 columns |
| 320 gutter | 8px |
| 320 column | 66px at exactly 320 |
| Base spacing | 4px |
| Body typography | ≥16px |
| Default line rhythm | ~24px |
| Preferred touch interaction | 48px |
| 320 forms | primarily one column / 4-of-4 |
| 320 table default | record representation |
| True 2D tables | isolated sheet scroll |
| Persistent rails at 320 | forbidden |
| Gallery panel | adaptive drawer/sheet |
| Information panel | adaptive inspector/sheet |
| AI panel | sheet/full-screen at 320 |
| Desktop rails | progressive enhancement |
| Reusable responsiveness | container-first |
| Application-shell responsiveness | viewport-first |
| i18n geometry | logical properties |
| CSS layout | Grid + Flex complementary |
| AI reordering | policy-constrained |
| Semantic source ordering | invariant |
| Density | information compression, not font compression |
| Glassmorphism | shell/context decoration only |
| Forms/tables | Flat 2.0 / high clarity |
| Large screen | more simultaneity, same workflow capability |

This directly answers the deeper design-discipline question behind the grid request:

**Flat 2.0, Bento, Card UI and Glassmorphism are visual/compositional languages. They do not define the underlying layout discipline.**

The underlying discipline should instead be:

> **semantic priority + constraint-based grid + progressive disclosure + container responsiveness + accessibility invariants + adaptive presentation.**

Flat 2.0 can then provide the operational visual language. Bento can be used for dashboard composition above the semantic grid. Cards can group information. Glass effects can differentiate transient contextual surfaces. None of them should determine whether information survives at 320px.

That is also the architecture most compatible with your 2030/2035 AI vision: the AI receives a structured system of priorities, constraints and allowable transformations instead of a blank canvas on which it is free to rearrange the enterprise application arbitrarily.

And for 320px specifically, the best test is brutally simple:

> **Remove 1,120 pixels from a 1440px desktop. Do not ask what the interface should look like. Ask what the user must still be able to know, decide and execute.**

If that question governs the grid, EA/EBP/EOP/EBM/ERX can remain genuinely operational at 320 CSS pixels rather than merely “responsive.”