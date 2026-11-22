# UI Modernization Plan — ONP DCMS

**Status:** Phase 1 (audit + design direction). No UI code has been changed.
**Date:** 2026-11-20
**Scope:** `frontend/` only. Backend, API contracts, persistence, and business rules are out of scope.

---

## 1. Project context

- **App:** Ospital ng Palawan Document Control Management System (ONP DCMS) — controlled
  document registry, review/approval workflow, distribution and acknowledgment,
  migration of legacy documents, records/retention, reports, and administration.
- **Users:** hospital staff on an institutional LAN — document owners, KMT/document
  control staff, reviewers, approving authorities, record custodians, system administrators.
- **Stack:** React 19 + TypeScript + Vite (`frontend/`), Laravel JSON API (`backend/`),
  hand-written CSS in `App.css` / `index.css`. No CSS framework, no router, no data library.
- **Runtime constraint:** offline-first. No CDN assets at runtime (`frontend/README.md`).
- **Specified usability constraints** (`DCMS_AI_Development_Pack/specifications/NON_FUNCTIONAL_REQUIREMENTS.md`):
  - Statuses and next actions must be clear.
  - Drafts, approved documents, released copies, obsolete versions, and uncontrolled
    copies must be distinguishable **visually and textually**.
  - Common desktop browser sizes are required; mobile support requirements remain unconfirmed.
- **Baseline target:** **Baseline Widely available**, recorded in `AGENTS.md`. Under this
  policy the Popover API and CSS anchor positioning are *not* baseline (anchor
  positioning is still unsupported in Firefox per the platform guidance), so they must be
  progressive enhancement with a working fallback — see §5.

---

## 2. What the current UI does well

Worth preserving rather than replacing:

- Real semantic HTML: `<table>` for registers, `<details>`/`<summary>` for disclosures,
  `<fieldset>`/`<legend>` in workflow role lists, `<label>` wrapping or bound to controls.
- A restrained palette that already avoids purple-gradient SaaS clichés.
- Server-driven permission filtering, so users only see authorized modules.
- Honest empty states; no invented institution data or fake metrics.
- `prefers-reduced-motion` handling already exists.
- A clear lifecycle vocabulary (draft → verified → approved → released) in copy.

The problem is not the concept — it is execution consistency, scale, and depth.

---

## 3. Audit findings

### 3.1 Broken: undefined design tokens (real bug, fix first)

`App.css` references seven custom properties that are never defined:

```
--brand-600  --brand-700  --ink-500  --ink-600  --ink-700  --ink-800  --ink-900
```

`index.css` defines only `--navy`, `--teal`, `--canvas`, `--line`, `--serif`.

Consequence: every declaration using them is invalid at computed-value time and falls
back to inherited/initial color. This silently breaks label, input, table, timeline, and
workflow text colors in the registry, workflow, and reports modules — and is why the
module screens look flatter and less legible than the shell. Any token work must begin
by making every referenced token real.

### 3.2 Two competing visual systems in one stylesheet

`App.css` contains two unrelated scales bolted together:

| | App shell (lines ~1–330) | Modules (lines ~330–465) |
|---|---|---|
| Body text | 8–11.5 px | 11–13 px |
| Labels | `.auth-label` 10 px | section labels 11–13 px |
| Radius | 7–14 px | 7–12 px |
| Color | hex literals (`#44545f`, `#7d8a93`) | tokens (`var(--ink-700)`, undefined) |
| Spacing | arbitrary px (9, 11, 13, 17, 19, 29, 42) | different arbitrary px |

This is the single biggest source of the "unfinished" impression: the overview and the
registry do not look like the same product.

### 3.3 Typography

- Micro-type is far below readable size: `.metric-copy small` **8 px**, `.brand-caption`
  8 px, `.nav-count` 10 px, `.panel-heading p` 9 px, most metadata 8–10 px. At 100 % zoom
  on a 1080p hospital workstation this is barely legible; at 200 % zoom the layout also
  reflows poorly because sizes are fixed.
- Font stack is the system default (`'Segoe UI', Arial` + Georgia) — stock, and the
  serif/sans split is applied inconsistently (some panels use Georgia for `h2`, others
  use the sans stack for the same role).
- Headings already use `clamp()`, but nothing else is fluid.
- No `text-wrap: balance` on headings, no `text-wrap: pretty` on body copy.
- No `font-variant-numeric: tabular-nums` where numbers align (metrics, dates,
  revision numbers, SHA hashes).

### 3.4 Layout and responsiveness

- Responsive behavior is media-query-only, at three breakpoints (1180 / 850 / 680 px),
  with hard-coded column counts (`repeat(4, …)`, `repeat(2, …)`). Components break based on
  the viewport, not their own width — so the same registry table behaves wrongly inside
  the admin two-column grid.
- Fixed `px` sizing throughout, no fluid rhythm, no logical properties
  (`margin-left`, `padding-right`, `left`, `width` used instead of inline/block forms).
- `100vh` used for `.app-shell`, `.main-area`, `.auth-shell` — will overflow under mobile
  browser chrome; `100dvh` is baseline.
- Overview uses two stacked four-column card grids (8 near-identical cards), the most
  generic pattern in the app.
- `@media` blocks duplicate each other's intent; `.welcome-row` flips to column at 1180 px
  even with plenty of room.

### 3.5 Overlays and navigation (JS-driven, no dismissal contract)

**Notification center** (`NotificationCenter.tsx`) — the clearest legacy pattern:

- A `<button>` toggles React state; the panel is a plain absolutely positioned `<section>`.
- No light dismiss (click outside), no `Escape` handling, no focus move or return,
  no `aria-expanded` / `aria-controls`, no `role` — so it is invisible to assistive
  technology as a disclosure and traps nobody, but also dismisses nobody.
- `z-index: 50` and manual `top/right` offsets; can be clipped by the sticky topbar and
  has no collision handling at viewport edges.

**Mobile sidebar** (`App.tsx`) — conditional render + transform + a separate
`.sidebar-backdrop` button. No focus trap, no `aria-expanded` on the trigger, no
`inert` on the page behind, no scroll lock, no `Escape`. Keyboard users tab into a
visually "closed" region and screen readers are not told anything changed.

**Page navigation** is local React state with `<button>` elements — no URL, no history,
no deep link. Changing this is an architectural change and is **explicitly out of scope**
for this modernization; flagged as Decision D-5.

### 3.6 Forms

- Fields rely on native `required` only. No `:user-invalid` styling, no `aria-invalid`,
  no field-level `aria-describedby`; server errors surface as one block message
  (`.config-message`, `.auth-error`), so users must hunt for the offending field.
- `.auth-input:focus { outline: none; … box-shadow }` removes the native outline and
  substitutes a low-contrast 14 % shadow ring. Global focus styling exists for `button`
  only — links, `summary`, `select`, and `input` have no consistent ring.
- No `field-sizing` on the many short textareas; every `<textarea>` has a fixed
  `rows={2}` and manual `resize: vertical`.
- Admin and configuration forms have no `autocomplete` hints, no `inputmode`, and label
  text is not always programmatically associated (some labels wrap, some use `htmlFor`
  with a matching id, some — e.g. `.managed-user-fields` — wrap a control whose `name`
  differs from the visible label).
- Related controls are not grouped; `assignment-form` is a 5-column grid of unrelated
  fields with no `<fieldset>`/`<legend>`.
- Required-vs-optional is inconsistent: some labels say "(optional)", most do not say
  which are required.

### 3.7 Tables and data views

- `.registry-table` has a `<thead>` but **no `<caption>`**, no `scope` attributes, no
  sortable affordance, and **no sticky header** — with 5 columns of nested version
  history it scrolls the header out of view immediately.
- Mobile behavior is horizontal scrolling of a table whose cells contain multi-hundred-
  pixel nested forms, `<details>`, download links, and action button clusters. It is
  effectively unusable below ~900 px.
- Row-level actions (`upload revision`, `assign code`, `replace files`, lifecycle
  actions, workflow decisions, distribution) are all nested inside a table cell as
  `<details>` forms. The information architecture is right (everything is an explicit
  separate action) but the presentation density is not.
- Loading states are bare text ("Loading document register…", no skeleton or reserved
  space → layout shift). Empty states exist and are good.
- Status is a `<span class="registry-status">` with a generic grey/blue pill for every
  value, so draft / released / obsolete / pending-code all look alike — directly at odds
  with the NFR requiring visual distinction.
- No responsive-data-table strategy, no `scrollbar-gutter`, no `overscroll-behavior` on
  the horizontal wrapper.

### 3.8 Motion and polish

- `transition: transform` + `translateY(-1px)` on every `.button:hover` is decorative
  movement, and it shifts text baseline on hover.
- No enter/exit animation for any overlay; the notification panel pops instantly.
- No `@starting-style`, no View Transitions, `prefers-reduced-motion` handled only by
  zeroing transition durations (transforms still jump).
- Several micro-details are implemented with text glyphs (`⇥` for sign-out,
  `×` for dismiss, hand-built `.empty-doc` divs) rather than consistent iconography.

### 3.9 Accessibility specifics

- `.status-dot` carries `aria-label="Local mode"` on a `<span>` with no role — the label
  is not exposed reliably. Same for `.top-user-avatar` (`aria-label` on a `<div>`).
- Icon-only sign-out uses `title` + `aria-label` but renders a glyph, and appears in the
  sidebar bottom rather than with the other topbar account controls.
- Page changes update `<h1>` text without moving focus or announcing the change; screen
  reader users get no confirmation that navigation happened.
- Dark mode is absent: no `color-scheme`, no `prefers-color-scheme`, and ~40 hard-coded
  `#fff` surfaces.
- `<meta name="theme-color">` is a single hard-coded navy; no light/dark variants.
- `:has()` is unused — available and ideal for field/row error and dirty states.
- Decorative inline style in `DocumentConfiguration.tsx`
  (`style={{ paddingLeft: … }}`) should be a logical `padding-inline-start`.

### 3.10 `legacy-scan` result (modern-web-guidance)

Scan over `frontend/src` (38 guides, 52 deterministic rules):

| Rule | File | Finding |
|---|---|---|
| `js/copying-array-methods` | `WorkflowConfiguration.tsx:69` | `[...template.versions].sort(...)` → `toSorted(...)` |

Verified against the rule's rubric: it is an `Array` copy, not a non-Array iterable, and
the project targets modern TS/ES2023+ (`typescript ~7.0.2`, `vite ^8`), so the finding
stands. Low risk, folded into Phase 3.

The scan covers only ~20 % of the guidance corpus; the patterns it cannot see are the
bulk of §3.5–3.9 above.

---

## 4. Design direction: “Ledger & Seal, glass edition”

### 4.1 Identity

A controlled-document system is an institution’s memory. The interface should read like
a **quality-record ledger**: warm paper, deep ink, hairline rules, tabular numbers, and
a single certification-stamp accent used with intent. Dense and calm, not airy SaaS.

Three deliberate signatures, applied consistently:

1. **Warm paper canvas + ink.** Never pure `#fff` on `#f7f9f8`. Light mode looks like
   good paper stock; dark mode looks like a soft-lit archive, not a neon dashboard.
2. **The rule, not the card.** Structure comes from 1 px rules, aligned columns, and
   outlined labels. Surfaces are flat with a hairline border and a *1 px* inset highlight;
   no 14 px-radius floating cards, no drop-shadow grids. Cards are reserved for genuinely
   self-contained objects.
3. **Stamped status.** Status is a small, uppercase, letterspaced stamp with a square
   marker and a defined color per lifecycle state — always paired with text, satisfying
   the "visually and textually" NFR without relying on color alone.

**Explicitly rejected:** purple/indigo gradients, heavy frost, emoji iconography,
oversized pill buttons, 16 px rounded card grids, teal-on-white "medical SaaS", and
Latin filler decoration. The DCMS domain does not need a mascot; it needs authority.

Glass is added narrowly (see §4.5): it is the material of the *chrome*, never of the
*record*. Dense hospital data must stay on opaque paper.

### 4.2 Palette (oklch), candidate values — contrast verified in Phase 2

Light:

| Token | Value | Role |
|---|---|---|
| `--paper` | `oklch(0.982 0.008 92)` | app canvas, warm off-white |
| `--surface` | `oklch(0.995 0.004 92)` | panels, inputs, table body |
| `--surface-sunken` | `oklch(0.962 0.010 92)` | table headers, inset strips |
| `--ink` | `oklch(0.26 0.035 250)` | primary text, headings |
| `--ink-muted` | `oklch(0.48 0.020 250)` | secondary text (AA on paper) |
| `--ink-faint` | `oklch(0.60 0.015 250)` | metadata ≥ 12 px only |
| `--rule` | `oklch(0.895 0.010 92)` | hairline borders |
| `--rule-strong` | `oklch(0.80 0.014 92)` | table header rule, section rules |
| `--seal` | `oklch(0.52 0.165 32)` | vermilion — primary action, focus ring, active nav |
| `--seal-soft` | `oklch(0.955 0.025 32)` | active-nav and selected tints |
| `--verify` | `oklch(0.50 0.075 175)` | in-review / verified / informational |
| `--pending` | `oklch(0.64 0.115 78)` | pending, due soon, awaiting action |
| `--danger` | `oklch(0.48 0.17 22)` | destructive and hard-block errors |
| `--success` | `oklch(0.47 0.09 155)` | completed / acknowledged |

Dark (`prefers-color-scheme: dark`):

| Token | Value |
|---|---|
| `--paper` | `oklch(0.215 0.018 255)` |
| `--surface` | `oklch(0.255 0.018 255)` |
| `--surface-sunken` | `oklch(0.195 0.016 255)` |
| `--ink` | `oklch(0.935 0.012 92)` |
| `--ink-muted` | `oklch(0.735 0.014 92)` |
| `--ink-faint` | `oklch(0.62 0.014 92)` |
| `--rule` | `oklch(0.345 0.014 255)` |
| `--rule-strong` | `oklch(0.44 0.016 255)` |
| `--seal` | `oklch(0.70 0.145 32)` |
| `--seal-soft` | `oklch(0.30 0.045 32)` |
| `--verify` | `oklch(0.72 0.085 175)` |
| `--pending` | `oklch(0.76 0.11 78)` |
| `--danger` | `oklch(0.68 0.15 22)` |
| `--success` | `oklch(0.72 0.09 155)` |

**Risk to agree on:** vermilion is both the action accent and semantically close to
"danger". Mitigation: `--seal` is used for *affordances* (buttons, focus, active nav,
eyebrow rules); `--danger` is used only for destructive confirmations and hard-block
validation, always with an icon and explicit wording. If this proves ambiguous during
Phase 3 review, the fallback is to move the primary action accent to ink (`--ink` button,
`--seal` reserved strictly for the stamp/active marker).

### 4.3 Type

**Direction:** editorial serif for document titles, record headings, and display; a
neutral grotesque for UI, metadata, and data. Serif = the document; sans = the system.

Because the app must not make external font requests, the type identity is **self-hosted**
variable fonts bundled by Vite (SIL OFL, latin subset, ~25–40 KB each):

- Display / headings / document titles: **Source Serif 4** (variable).
- UI / body / data: **Archivo** (variable) — humanist-neutral, strong at 12–15 px,
  excellent tabular figures.

Fallbacks preserved (`ui-serif, Charter, Georgia, serif` and
`ui-sans-serif, system-ui, "Segoe UI", sans-serif`) so a failed font load never changes
layout. **If bundling fonts is rejected, the direction degrades to a refined system-only
stack** (`ui-serif`/Charter + `system-ui`), which is coherent but less distinctive.

Scale (root stays 16 px; sizes in `rem`):

| Token | Size | Use |
|---|---|---|
| `--text-2xs` | `0.6875rem` (11) | uppercase micro-labels only, never body |
| `--text-xs` | `0.75rem` (12) | metadata, table captions, helper text |
| `--text-sm` | `0.8125rem` (13) | dense table cells, secondary UI |
| `--text-base` | `0.9375rem` (15) | body / form controls |
| `--text-md` | `1.0625rem` (17) | panel headings |
| `--text-lg` | `clamp(1.25rem, 1rem + 0.6cqi, 1.5rem)` | section titles |
| `--text-display` | `clamp(2rem, 1.3rem + 2.4cqi, 3.125rem)` | page `h1` |

Rules: no text below 12 px except 11 px uppercase labels; `line-height` 1.45–1.6 for
body, 1.1–1.2 for display; `tabular-nums` on all counts, dates, codes, and hashes;
`text-wrap: balance` on headings, `text-wrap: pretty` on paragraphs (harmless degradation).

### 4.4 Space, radius, elevation, motion

- **Spacing:** 4 px base — `--space-1`(4) … `--space-3`(12), `--space-4`(16),
  `--space-5`(20), `--space-6`(24), `--space-8`(32), `--space-10`(40),
  `--space-12`(48). Section rhythm via `clamp(1.5rem, 1rem + 1.5cqi, 2.5rem)`.
- **Radius:** `--radius-xs` 2 px (stamps, checkboxes), `--radius-sm` 4 px (inputs,
  buttons), `--radius-md` 6 px (panels), `--radius-lg` 10 px (dialogs only).
  The current 9–14 px everywhere is what makes it read as a generic template.
- **Elevation:** flat by default — `1px solid var(--rule)` plus
  `inset 0 1px 0 rgb(255 255 255 / .6)`. Shadow exists only for top-layer elements:
  `--shadow-overlay: 0 1px 2px oklch(0 0 0 / .06), 0 16px 40px -12px oklch(0 0 0 / .18)`.
- **Motion:** `--dur-fast` 120 ms (hover, focus), `--dur-base` 180 ms (disclosure,
  dialog), `--ease-out` `cubic-bezier(.2, .8, .2, 1)`, `--ease-in-out` for position.
  Motion only communicates state (open/close, selection, progress). No hover lift, no
  parallax, no decorative entrance sequences. Everything collapses under
  `prefers-reduced-motion: reduce`.

### 4.5 Glass (chrome and floating layers only)

A subtle backdrop makes translucency legible: fixed, layered radial gradients over
`--paper` — faint ink-blue, vermilion, and teal glows at low alpha. No blobs.

Tokens (light / dark): `--glass-bg`, `--glass-border`, `--glass-highlight`,
`--glass-shadow`, `--glass-blur` (16 px), `--glass-saturate` (135 %), and
`--glass-radius` (`--radius-md`, one value for every glass surface).

Applied to: sidebar, top bar, notification popover, dialogs, popover menus, and the
overview summary strip. **Not** applied to registry tables, workflow screens, forms,
report content, or document previews.

Rules:

- `.surface-glass` is opaque by default; the translucent fill, blur, and saturate are
  inside `@supports ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px)))`.
- `prefers-reduced-transparency: reduce` → solid surfaces.
- `forced-colors: active` → no glass, system colours, visible 1 px border.
- `prefers-reduced-motion: reduce` → no blur or opacity transitions.
- Never nest glass in glass. Never blur a large scrolling region. Never animate blur.
- Light mode uses a slightly *darker* hairline than the fill, because a brighter border
  on a near-white glass fill is invisible; dark mode uses the brighter hairline.
- Fill opacity leans high (0.82 light / 0.74 dark) for glare and older displays.

### 4.6 Signature compositions

- **Sidebar as a spine:** hairline vertical rule, section labels in 11 px uppercase,
  active item marked by a 2 px `--seal` inline-start bar + `--seal-soft` fill, no pill.
- **Overview as a register summary:** replace the 8-card grid with one bordered strip of
  4 cells separated by vertical rules (subgrid-aligned label → figure → footnote), plus a
  single ledger panel. Fewer boxes, more information.
- **Registry:** sticky header, strong header rule, `tabular-nums` code column, status as a
  stamp, row detail expands into a full-width ledger row rather than a cramped nested card.
- **Auth:** a single centered sheet on paper with a ruled header and the seal mark; no
  floating rounded card with a radial gradient.

---

## 5. Legacy pattern → modern replacement map

| # | Current pattern | Replacement | Baseline status | Fallback required |
|---|---|---|---|---|
| 1 | Notification `<div>` + React toggle, no dismiss | `popover="auto"` + anchored panel; light dismiss and `Escape` free | Popover = *newly available* | Yes — see below |
| 2 | Manual `top/right` positioning | CSS anchor positioning (`position-area`, `position-try-fallbacks`) | **Limited**; no Firefox | Yes — CSS absolute fallback, no polyfill |
| 3 | Mobile sidebar drawer + backdrop div | `<dialog>.showModal()` with `::backdrop`, native focus trap + `inert` | Widely available (2022) | No |
| 4 | Fixed `@media` column counts | Container queries + `repeat(auto-fit, minmax(…))`, `cqi` fluid type | Widely available (2023) | No |
| 5 | Ragged label/control alignment | `subgrid` on form grids and summary strips | Widely available (2023) | Single-valued `grid-template-*` first |
| 6 | JS column paddings for category depth | `padding-inline-start` from a CSS custom property | Baseline | No |
| 7 | No error/dirty field styling | `:user-invalid`, `:has()`, `aria-invalid`, `aria-describedby` | `:has()` widely available | Text + `aria-describedby` |
| 8 | Fixed `rows={2}` textareas | `field-sizing: content` with `min-block-size` | **Not baseline** | Yes — `rows` attribute remains |
| 9 | Instant overlay appearance | `@starting-style` + `transition-behavior: allow-discrete` | **Not baseline** | Content appears without animation |
| 10 | `transform` hover lift | Color/border/background transition only | Baseline | n/a |
| 11 | `100vh` | `100dvh` | Widely available (2022) | No |
| 12 | Generic status pill | Semantic stamp per lifecycle state, text + color | CSS only | No |
| 13 | Horizontal-scroll-only table | Sticky header + container-query row reflow (labelled stacked rows) | Widely available | Table stays scrollable |
| 14 | `[...arr].sort()` | `toSorted()` | ES2023 | n/a |
| 15 | Page switch, no announcement | Focus the `<h1>`/`tabindex="-1"` + live region for the view name | Baseline | n/a |
| 16 | Optional View Transitions | Same-document `startViewTransition` on page change | **Limited** | Feature-detected; no-op |

**Fallback rules (per `AGENTS.md`):** anchor positioning is gated in
`@supports (position-area: …)`; `inset: auto`/`margin: 0` are *inside* the `@supports`
block so unsupported browsers keep native popover centering. No polyfills unless
approved (the anchor-positioning polyfill additionally can’t use implicit anchors or
`position-area` with `position-try-fallbacks`, so shipping it would dictate the CSS).

---

## 6. Execution plan (after approval)

Each step is a small, independently reviewable change. After each: `npm run build`
(`tsc -b` + `vite build`), `npm run lint`, and the stated keyboard/visual check.
No frontend test runner exists and **none will be added** (no new dependencies); manual
verification is the check, plus the existing backend suite is untouched.

### Phase 2 — Foundation (1 commit)

1. `index.css`: define the full token set (color/light+dark, type, space, radius, shadow,
   motion), `color-scheme: light dark`, `@media (prefers-color-scheme: dark)` overrides,
   base element styles, a single focus-visible ring, `::selection`, font loading.
2. Fix the seven undefined tokens by pointing them at the new system — this alone
   repairs the module text-color bug.
3. Base typography: body 15 px, headings with `text-wrap: balance`, `tabular-nums` where
   numbers align, logical properties, `100dvh`.
4. `index.html`: `color-scheme` meta, split `theme-color` for light/dark, `preconnect`
   none (self-hosted), correct `title`.
5. Add the two self-hosted fonts (Decision D-3) or the system-only fallback stack.

**Verify:** app renders in light and dark; no declaration resolves to an undefined
variable; contrast of `--ink`/`--ink-muted`/`--seal` on `--paper`/`--surface` checked at
AA (4.5:1 body, 3:1 large and UI).

### Phase 3 — Components, one at a time

Order chosen so shared primitives land before the screens that need them:

1. **Shell** — sidebar spine, topbar, nav active state, user block, `dvh`, responsive
   disclosure semantics (`aria-expanded`, `aria-controls`). Mobile drawer → `<dialog>`
   (step 3 does the modal conversion; step 1 keeps current behavior with correct ARIA).
2. **Auth screens** — sign-in and forced-password-change sheets; field-level validation,
   `autocomplete`, `:user-invalid`, focus ring, error `aria-describedby`.
3. **Overlays primitive** — `NotificationCenter` on `popover="auto"`, anchored via
   `@supports`; returns focus to the trigger; unread count exposed as text; add
   `<dialog>`-based confirmations for destructive lifecycle actions if needed.
4. **Overview** — ledger summary strip (subgrid, container queries), panel headings,
   empty-state illustration cleanup, removal of decorative hover lift.
5. **Registry** — `<caption>`, `scope`, sticky header, status stamps, container-query row
   reflow, row actions moved from `<details>`-in-cell into a `<dialog>` (one dialog
   component reused by all action forms), preserved `form`/`name`/`onSubmit` wiring.
6. **Administration forms** — `DocumentConfiguration`, `UserAdministration`,
   `InstitutionConfiguration`, `WorkflowConfiguration`: `<fieldset>` grouping, subgrid
   alignment, `:user-invalid`, remove inline `paddingLeft`, `toSorted()`.
7. **Remaining modules** — Distribution, Migration, External references, Records,
   Reports: align to the same table/panel/status primitives, sticky headers, loading
   states with reserved space.
8. **Optional polish** — View Transitions for page change (feature-detected, reduced-motion
   opt-out), page-change focus/announcement, `@starting-style` where the fallback is a
   plain appearance.

**Guardrails for every step**

- No changes to `api.ts`, request/response shapes, endpoint paths, permission checks,
  workflow transitions, or any `backend/` file.
- Every form keeps its `name` attributes, `encType`, `autoComplete`, and handler logic.
  Controlled inputs keep their state shape.
- Keyboard contract checked per component: Tab order follows DOM order, `Escape` closes
  overlays, focus returns to the invoking control, visible ring at 3:1.
- Status never conveyed by color alone.
- Dark mode checked at the same time, not retrofitted later.

---

## 7. Out of scope

- Backend, migrations, API contract, authorization, workflow engine.
- Adding a router, query/data library, CSS framework, component library, or test runner.
- Changing IA/labels/copy beyond punctuation-level consistency.
- Replacing `<details>` where it is the correct disclosure primitive.
- Mobile-first redesign: the NFR leaves mobile unconfirmed. Mobile improvements are
  included because the CSS work makes them nearly free, but desktop is the acceptance
  target.

---

## 8. Decisions needed to proceed

| ID | Decision | Recommendation |
|---|---|---|
| **D-1** | Baseline target | **Baseline Widely available** (already written to `AGENTS.md`). Consequence: Popover, anchor positioning, `@starting-style`, `field-sizing`, View Transitions are progressive enhancement only. |
| **D-2** | Accent color | Vermilion “seal” as action accent, with `--danger` reserved for destructive/hard-block states. Fallback: ink primaries if it reads as an error color in review. |
| **D-3** | Self-hosted fonts | Source Serif 4 + Archivo, bundled (~50–80 KB total, OFL, no runtime request). Alternative: system-only stack, zero new assets. |
| **D-4** | Replacing overview metric cards with a single summary strip | Recommended — biggest single departure from the generic template look. |
| **D-5** | URL/deep-link architecture for page navigation | **Defer.** Out of scope for this pass; currently state-only and unchanged. |

---

## 9. What “done” looks like

- One coherent visual language across shell, registry, and admin (the two-system split in
  §3.2 is gone).
- Readable at 100 % and 200 % zoom; nothing below 12 px except 11 px uppercase labels.
- Light and dark mode both ship, both AA.
- Notification panel and mobile navigation dismiss with `Escape`, manage focus, and work
  keyboard-only.
- Registry readable without horizontal scrolling at the widths the hospital actually uses.
- Lifecycle states distinguishable by text + form + color.
- `npm run build` and `npm run lint` clean; no change in any network request shape.

---

## 10. Copy audit

Ongoing log, updated screen by screen. Rule applied: keep labels, rules, errors, statuses, captions, and procedural wording; remove restatement, taglines, and build-process text.

### 10.1 Global scan (whole frontend)

| Item | Found | Action |
|---|---|---|
| Page `<title>` | `ONP DCMS · Document Control` | Set to `ONP DCMS`. |
| Favicon | `public/favicon.svg` was the Vite logo and was never linked from `index.html` | Replaced with the DCMS seal mark; linked as `rel="icon"`. |
| Unused scaffold assets | `src/assets/react.svg`, `src/assets/vite.svg`, `src/assets/hero.png`, `public/icons.svg` (Bluesky/social symbol set) | Deleted; nothing referenced them. |
| `package.json` description | absent | `ONP DCMS web client`. |
| `frontend/README.md` | "React + TypeScript + Vite foundation…", "responsive foundation preview" | Rewritten to "Web client for…" and the preview sentence removed. |
| AI/authorship markers | No occurrences of AI, generated, Claude, Copilot, GPT, assistant, vibe, or Lorem ipsum in source. `package-lock.json` contains `github.com/sponsors/ai` dependency metadata only. | Nothing to change. |
| Code comments | Only CSS comments explaining glass fallbacks and token intent | Kept — they explain why, not the generation process. |

### 10.2 Shell (Phase 3, step 1)

**Removed**

- Page eyebrow (`Workspace overview`, `Controlled documents`, …) on all 13 views — it repeated the sidebar label and the page heading for a third time.
- Top-bar breadcrumb (`Workspace › {eyebrow}`) — a fourth repetition of the same name.
- Every page description (`A clear view of controlled documents…`, `Find and manage the institution’s controlled document records…`, etc.) — generic restatement of the title.
- Overview tagline heading `Your documents, under control.` — replaced with `Overview`.
- `DEVELOPMENT PREVIEW` pill — environment noise, not hospital information.
- Preview banner (`Foundation preview` / `This workspace has no live records yet…`) and its dismiss button — build-state notice; module and empty states already say what is missing.
- Footer (`DCMS · Institutional Document Control`, `Local-network ready · Foundation preview`) — duplicate branding plus scaffold wording.
- Sidebar caption `Ospital ng Palawan` — duplicated the institution card directly below; now `Document control`.
- `Private by design` / `Built for your local network` — tagline; now `Local network` / `No internet required`.
- `ONP WORKSPACE` / `Ospital ng Palawan setup needed` and the chevron (which implied a menu that does not exist) — now `INSTITUTION` / `Ospital ng Palawan` / `Setup required`.
- Placeholder nav count `—` on My tasks — a dash standing in for a number.
- Sign-out glyph `⇥` with a `title` tooltip — now a visible `Sign out` button.
- Module placeholder: `MODULE FOUNDATION`, `{title} is being prepared`, the “approved DCMS scope” sentence, the configurable-settings note, and the divider — replaced with one sentence and the return action. No navigation entry reaches this branch: all 13 entries were exercised in the headless suite and each loads its own view, so no entries were hidden.
- Button `Set up ONP` — now `Set up institution` (the abbreviation is not explained anywhere on the screen).

**Kept, and why**

- One `<h1>` per view carrying the page name — the only page heading, required for document structure.
- Sidebar labels `WORKSPACE` and `ADMINISTRATION` and the nav item names — navigation, not prose.
- `Setup required` — actual configuration state, not decoration.
- `Local network` / `No internet required` — deployment facts that matter on the ward.
- User name, role, and `Sign out` — identity and the action itself.
- All `aria-label`s, including the navigation dialog and the mobile menu trigger; the avatar and status dot are now `aria-hidden` with equivalent visually hidden text instead of labels on non-interactive elements.
- Overview empty-state and principle copy — left for the overview pass; the principle is a document-control rule, not a slogan.

**Unsure / decisions**

- The sidebar label and the `<h1>` still name the same screen. Both were kept: the sidebar shows where you are, the `<h1>` is the page’s only heading. The redundant third and fourth copies (eyebrow, breadcrumb) were removed instead. If zero repetition is wanted, the alternative is dropping the `<h1>` on module pages and promoting the module’s first heading — this would weaken the heading outline.
- `Setup incomplete` read as an error. Renamed to `Setup required` after review.
- The placeholder line “This module is not connected to live records yet.” is now unreachable because every nav entry has a view; it stays as a fallback. If a module regresses to a placeholder, hide its nav entry rather than explaining the build state.
- Removing the preview banner drops the only explicit “no live records” notice; the module and empty states carry that information instead.

### 10.3 Auth (Phase 3, step 2)

**Removed**

- `SECURE LOCAL SIGN-IN` eyebrow and `Welcome back.` heading — the eyebrow repeated the sign-in context and the greeting said nothing about the task.
- `Sign in with your individual DCMS account.` description — restated the form.
- `If you cannot sign in, contact a DCMS system administrator.` helper line — removed at review; the same guidance lives in `README_AUTHENTICATION.md`.
- `OSPITAL NG PALAWAN` brand caption and `ACCOUNT SECURITY` caption on the password-change screen — institution name and a label that repeated the form’s purpose.
- `PASSWORD UPDATE REQUIRED` eyebrow on the password-change screen.

**Kept**

- `DCMS` as the only visible product name; it is now the page’s `<h1>` so the sign-in page still has a heading.
- `Email`, `Password`, `Sign in`, `Temporary password`, `New password (at least 12 characters)`, `Confirm new password`, `Update password` — labels and actions.
- Real error messages only: `Enter your email address.`, `Enter an email address in the format name@example.org.`, `Enter your password.`, and the server’s generic `The email or password is incorrect.`
- `Show` / `Hide` password toggle with `aria-pressed`, kept inside the password field.

**Behaviour**

- Validation runs on blur and on submit, clears on input once valid, and moves focus to the first invalid field.
- `required` is retained; `aria-invalid` and `aria-describedby` connect each message; messages use `role="alert"`.
- Email carries `autocomplete="username"`, the password `autocomplete="current-password"`; the email field disables autocorrect, autocapitalisation and spellcheck.
- The previous `outline: none` on inputs was replaced with a 2px seal focus ring.
- Failed sign-in remains generic for a wrong password and an unknown account (verified in the browser suite).

**Unsure**

- The password-change screen still explains the temporary password (“Your administrator provided a temporary password. Replace it before continuing.”). It is procedural, not a tagline, so it stayed; the eyebrow above it was removed.

### 10.4 Notification popover (Phase 3, step 3)

**Removed**

- `Notifications` repeat inside the panel: the heading is kept once as the panel’s `<h2>`, and the panel is named by it.
- The old React-state panel and its generic `No notifications yet.` copy — now a native popover with `No notifications.` only when the list is empty.

**Kept**

- `Notifications` heading and trigger name; the trigger announces `Notifications, 1 unread` when a count exists.
- Notification title, body and local timestamp — record content.
- `Mark read` per unread item, with the notification title appended for screen readers so repeated buttons are distinguishable.
- `Unread` as a visible text label next to the title, plus a 2px seal edge; unread is not signalled by colour alone.
- `Close notifications` control for explicit dismissal.

**Unsure**

- The panel uses the glass primitive because measured contrast against the rendered backdrop passes AA (lowest 6.13:1 light, 6.45:1 dark). If the backdrop ever carries busier content underneath, the fallback is the opaque `--surface`.
- The installed Chromium, Firefox, and WebKit all expose `popover` and `position-area`, so no real engine in the matrix takes the fallback. The fallback remains covered by deleting `HTMLElement.prototype.popover` before load; open, Escape, outside click, close, and focus return pass there.
- Real-engine difference found: WebKit does not return focus to the invoker when the popover closes, and it leaves focus on the hidden panel. The `toggle` handler now restores focus explicitly. WebKit also does not restore focus when a modal `<dialog>` closes, so the drawer restores it on `close`. Both were failing tests before the fixes.
- Contrast sampling now uses `page.screenshot({ scale: 'css' })`; WebKit screenshots at device scale factor 2, which had made the sampler read the wrong pixels.

### 10.5 Empty states and module inventory

- No navigation entry is a placeholder: all 13 were visited in the browser suite and each opened its own view with a matching `<h1>` and no console error. Nothing was hidden; the catch-all `module-placeholder` branch remains for a future module without a view.
- Empty states now state the absence plainly:
  - Overview: `No records yet`, `No activity recorded yet.`, `No tasks assigned yet.`
  - Registry: `No documents recorded in your scope yet.`
  - Distribution: `No document versions have been distributed to you.`
  - Migration: `No migration batches recorded yet.` / `No source files recorded in this batch yet.`
  - External references: `No external references recorded yet.`
  - Records: `No operational records recorded yet.`
  - Reports: `No audit events recorded yet.`
  - Document configuration: `No document types configured.` / `No document categories configured.`
  - Workflow configuration: `No workflow templates configured.`
  - Users & access: seeding instruction retained — it explains why the list is empty.
  - Institution setup: `No organizational units have been added.`

### 10.6 Overview (Phase 3, step 4)

**Removed**

- The two card grids: five role cards and four static document metric cards. Eight bordered cards said less than one register strip.
- Four static metric labels (`Controlled documents`, `Awaiting your review`, `Reviews coming due`, `Acknowledgments pending`) and their invented icon chips. They duplicated the role counters and always showed `—`.
- Repeated `No records yet` under every metric card; the note now appears only where the count is zero or unavailable.
- Per-card icon squares (`metric-icon`, `metric-blue`, `metric-amber`, `metric-violet`, `metric-teal`); the strip uses no icons.

**Kept**

- The role counter labels supplied by `/dashboard`, unchanged in wording.
- `No activity recorded yet.`, `No tasks assigned yet.`, the setup steps, and the `DCMS PRINCIPLE` ledger panel.
- `Local network` / `No internet required` in the sidebar.

**Added**

- One ruled strip: label → figure → note, with `subgrid` alignment and `tabular-nums` figures.
- Loading (`aria-busy`, `Loading` notes), empty (`No summary counts are available for this role yet.`), and error (`Summary counts could not be loaded.` plus `Retry`) states.
- `aria-labelledby` names the region; the heading is visually hidden.

**Unsure**

- The approved composition describes four cells. A system administrator has five role counters, so the strip renders every counter the API returns (four for most roles, five for this one) rather than discarding one.

### 10.7 Document registry (Phase 3, step 5)

**Removed**

- Every action form that was hidden inside the title and status cells (`Versions`, `Assign official code`, `Upload revision`, lifecycle actions, distribution). They now appear only after `Details`, inside a full-width detail row.
- The duplicate visible table caption; the caption is now screen-reader-only because the panel heading already states the same name.
- The generic rounded status pill; each status is a stamped label with a tone edge and the status text.
- The version list that used to sit inside the title cell; versions now live in the detail row, and the summary row header shows the latest revision label or `—`.

**Kept**

- Column labels `Code / revision`, `Title`, `Owner / unit`, `Status`, and every action label and message in the detail row.
- All lifecycle, workflow, distribution, upload, and code-assignment copy — moved, not rewritten.
- `No documents recorded in your scope yet.` as the empty state.

**Added / changed**

- `<caption>` plus `scope="col"` headers and a `scope="row"` code cell.
- Sticky column headers inside a keyboard-focusable `role="region"` scroller named by the caption.
- An `Effective` date column, end-aligned with `tabular-nums`; code figures also use tabular numerals.
- Fixed column proportions so all six columns fit at 1024 and 1280 without page-level overflow.
- `Details` / `Hide details` per row with `aria-expanded` and `aria-controls`, revealing the existing forms in place.
- Stamps: `Released` (success tone), `Draft`/`Pending code` (pending tone), `Returned for revision`/`Rejected`/`Expired` (danger tone), neutral for everything else; the word is always present.

**Unsure**

- A detail row rather than a popover action menu. The rule allowed either; the row keeps long forms and history readable and avoids a scrollable menu inside a scrollable table.

### 10.8 Remaining screens

Administration forms, workflow configuration, reports, and the remaining modules still carry template-style copy and will be audited as each is restyled. Per-view document titles (`<view> · ONP DCMS`) now update on navigation for every view.

### 10.9 Development seed copy (Phase 3, step 2 follow-up)

**Removed**

- `test@example.com` from `DatabaseSeeder` in every environment except `local` and `testing`. Production seeding now creates no account with a published password; `ProductionSeedSafetyTest` asserts it.

**Added, and why the wording is like this**

- Fictional sample records, created only by `php artisan dcms:dev-sample-documents` (refuses production and any non-SQLite database) and never from `DatabaseSeeder`: `Sample — Awaiting official code`, `Sample — Draft procedure`, `Sample — Returned procedure`, `Sample — Released procedure`; units `Sample Operations Unit` and `Sample Records Unit`; accounts named `Sample Process Owner`, `Sample KMT`, and so on at `@example.test` addresses.
- The `Sample —` prefix and the `Sample` display names are deliberate: any screenshot taken from a development database shows that the record is invented. The generated PDF body says `Fictional local sample procedure <CODE>` for the same reason.
- Document codes keep the institutional pattern (`DEMO-TRN-OPS-PROC-012`) so the numbering, table, and copy behaviour under test are the real ones.

**Kept**

- The same lifecycle copy as production runs (statuses, workflow decisions, distribution states) — the sample data exercises the real services, so no test-only wording leaks into the interface.
- `DatabaseSeeder`’s role, permission, and institution baseline in every environment.

### 10.10 Forms across modules (Phase 3, step 3)

**Added**

- Field hints that state a rule rather than restating the label: `A reason is required to return a document or to resolve submission warnings.`, `The reason is recorded in the distribution history.`, `The reason is recorded against this version.`, `Choose one or more units, roles, or individual recipients.`, `At least 12 characters. The account must replace it at first sign-in.`, `At least 12 characters. The user must replace it at next sign-in.`, `A time zone name, for example Asia/Manila. It controls displayed timestamps.`
- A field-level error under the workflow decision reason, connected with `aria-describedby` and `aria-invalid`, replacing the top-of-panel-only message for that validation. It keeps `role="alert"`.

**Changed**

- Every text, select, and textarea control in the registry, lifecycle, distribution, and workflow forms is 16px, so mobile browsers do not zoom on focus; the administration forms were already 16px.
- `:user-invalid` on those controls now paints the danger border, and `:focus-visible` paints the 2px seal ring on top of it; no control relies on `outline: none`.
- Submit buttons state what they are doing while disabled (`Assigning code…`, `Saving draft…`, `Issuing…`, `Exempting…`, `Acknowledging…`, `Recording…`, `Saving…`, `Creating…`, `Adding…`), and disabled buttons show a not-allowed cursor and reduced opacity. Double submission was already prevented by the `busy` flag; the label now says why the button is inert.
- `autocomplete` is `off` on administrator-entered names, emails, codes, time zones, and reasons, and `new-password` on every temporary password. Names still carry no autofill semantics because an administrator is not entering their own details.
- The category tree indentation uses `padding-inline-start` instead of `padding-left`.
- Below 1100px the register drops its 640px minimum width, reserves no scrollbar gutter, and gives the action column the space it needs, so at 1024px the code, title, status, and actions all fit inside the register scroller on Chromium, Firefox, and WebKit.

**Kept**

- Every existing label, required marker, and server error message; the guides ask for correct wiring, not new wording.
- `aria-label`s on the repeated per-recipient and per-stage controls, which already distinguish otherwise identical buttons.

**Reported, not changed**

- `Replace current files` is unreachable from the detail row: it requires `canUpload` (only when the latest version is released or absent) and a version in `draft`/`returned_for_revision`, and those conditions never hold together. The form is left in place and reported rather than silently deleted; fixing it is a product decision.
- The sticky top bar can cover a control at the top of the register’s internal scroller. Tests drive those controls with the keyboard, which works, but a pointer user scrolling a long detail row can still meet the overlap.
