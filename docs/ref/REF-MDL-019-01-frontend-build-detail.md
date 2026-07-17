---
ref: REF-MDL-019-01
adr: MDL-019
title: 'AMD Chunk Table & Vite Plugin Detail'
lang: en
---

# REF-MDL-019-01: AMD Chunk Table & Vite Plugin Detail

> Detail supporting [MDL-019](../decisions/MDL-019-frontend-moodle-integration.md). Reconstructed from the `moodle-local_middag` legacy vault (`ADR-807` §17-18, no dedicated REF companion — extracted directly from the ADR body).

## AMD chunk table

| Chunk                      | Contents                             | Loading   |
|----------------------------|--------------------------------------|-----------|
| `middag-app.min.js`        | Entry + app code + registries        | Immediate |
| `react-vendor-lazy.min.js` | react, react-dom, `@inertiajs/react` | Lazy      |
| `react-ui-lazy.min.js`     | Radix UI primitives                  | Lazy      |
| `react-table-lazy.min.js`  | TanStack Table                       | Lazy      |
| `middag-ext-{slug}.min.js` | Extension chunks (non-core, via CDN) | Lazy      |

## Vite plugin (`moodle-amd`)

Rewrites relative import paths between compiled chunks into the `local_middag/{name}` format Moodle's AMD loader expects. Duplicates build output into `amd/src/` for dev mode specifically to avoid running two separate `watch` processes (one for `amd/build/`, one for `amd/src/`).

## Theme bridge mechanics

`$PAGE->theme->settings->brandcolor` is read server-side and injected as `--middag-brand` in `<head>`. Tailwind's semantic tokens (`--primary` and siblings) reference `--middag-brand` conditionally, gated by the `local_middag/inherit_theme_colors` admin checkbox — when off, the product's own fixed theme tokens apply instead.

## Why this lives in `moodle`, not `ui` (rationale carried from the source)

Both decisions are specifically about how the frontend adapts *to Moodle* — reading Moodle's own theme config, packaging for Moodle's own AMD loading mechanism — not a reusable UI mechanism. That is the same criterion used to classify the rest of this boundary documentation set.
