# Repository guidance

ONP DCMS (Ospital ng Palawan Document Control Management System): Laravel API in
`backend/`, React + TypeScript + Vite client in `frontend/`.

Backend work must additionally follow `backend/AGENTS.md`. Governance and
specification documents live in `DCMS_AI_Development_Pack/`.

## Browser support

**Target: Baseline Widely available.**

Do not assume Baseline *Newly available* features are usable. Features outside the
widely-available set may be adopted only as progressive enhancement: feature-detect
them (`@supports`, `CSS.supports`, or a DOM capability check), keep a working
fallback, and never add a polyfill or runtime dependency without explicit approval.

As of 2026-11, this means:

- Safe without fallback: `<dialog>`, container queries, subgrid, `:has()`,
  `clamp()`, `dvh`, logical properties, `prefers-color-scheme`,
  `prefers-reduced-motion`.
- Must be progressive enhancement: the Popover API, CSS anchor positioning,
  `@starting-style`, `field-sizing`, `text-wrap: balance`/`pretty` (harmless
  degradation), and View Transitions.

## Offline-first constraint

The app runs on an institutional LAN without internet access. No runtime request
may depend on a CDN: no web fonts, scripts, or stylesheets fetched from a remote
host. Fonts, if any, must be self-hosted and bundled.

## Glass surfaces

`.surface-glass` in `frontend/src/index.css` is the single glass implementation.
Its base style is an opaque surface; translucency is applied only inside the
`@supports` backdrop-filter block, and is removed for
`prefers-reduced-transparency: reduce` and `forced-colors: active`.

- Glass is limited to chrome and floating layers: sidebar, top bar, notification
  popover, dialogs, popover menus, and the overview summary strip.
- Do not apply glass to registry tables, workflow screens, forms, report content,
  or document previews. Those stay on opaque paper.
- Never nest a glass surface inside another, never blur a large scrolling region,
  never animate the blur radius, and keep only a few glass layers on screen.
- Check text contrast against the worst-case backdrop, not the average. When in
  doubt, raise the fill opacity.

## UI conventions

Design tokens are CSS custom properties defined in `frontend/src/index.css`.
Do not hard-code colors, type sizes, spacing, radii, or motion durations in
component styles. The planned modernization is documented in
`docs/ui-modernization-plan.md`.
