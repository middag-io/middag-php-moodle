---
id: MDL-019
title: 'Frontend Moodle Integration: Theme Color Inheritance & AMD Build Output'
status: accepted
date: 2026-04-15
domains: [moodle, frontend]
related: [MDL-018]
supersedes: []
superseded_by: null
lang: en
---

# MDL-019: Frontend Moodle Integration — Theme Color Inheritance & AMD Build Output

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-807`, decided 2026-04-15) — specifically the two Moodle-boundary fragments (§17-18) extracted from that broader frontend architecture ADR. This is an archaeology pass, not a new decision — dates and rationale are historical. ADR-807's remaining ~23 sections (page-contract/composition mechanism, product shell/visual identity) live in the `middag-io/ui` and `middag-io/core`-equivalent libraries' own decisions records, not here — split by explicit judgment call during this consolidation pass (merging what the source material treated as two adjacent subsections, since both describe how the same frontend build adapts to the Moodle host specifically, sharing one rationale).

## Context

The frontend (built in `middag-io/ui`/`middag-io/framework`, consumed here) needs two Moodle-specific adaptations that are neither generic UI mechanism nor product visual identity: reading the active Moodle theme's brand color so the product can optionally match it, and packaging its build output for Moodle's own AMD/RequireJS module-loading mechanism rather than a generic bundler target.

## Decision

**Theme color inheritance**: the frontend optionally inherits the active Moodle theme's primary color, read from `$PAGE->theme->settings->brandcolor` and injected as the `--middag-brand` CSS custom property in `<head>`. Tailwind's semantic tokens (`--primary`, etc.) map onto `--middag-brand` when inheritance is enabled. An admin setting, `local_middag/inherit_theme_colors` (checkbox), controls whether MIDDAG inherits the Moodle theme or uses its own fixed theme. **AMD build output**: the frontend compiles via Vite with direct AMD output into `amd/build/`, with `-lazy`-suffixed chunks loaded on-demand through Moodle's native `requirejs.php` mechanism. A dedicated Vite plugin (`moodle-amd`) rewrites relative paths between chunks into the `local_middag/{name}` format Moodle expects, and duplicates the output into `amd/src/` for dev mode, eliminating a duplicate `watch` process.

## Consequences

- The product can visually match the host Moodle site's brand color without hardcoding it, while still allowing sites that want MIDDAG's own fixed identity to opt out via the admin setting.
- Build output is directly consumable by Moodle's native module loader with no manual repackaging step, at the cost of a bespoke Vite plugin that must be kept in sync with Moodle's `requirejs.php` expectations.
- Lazy-loaded chunks (React vendor, UI primitives, table library, extension bundles) keep the immediately-loaded entry small, deferring the bulk of the frontend payload until it is actually needed.

## Out of scope

- The page-contract/composition mechanism this frontend build serves — lives in `middag-io/ui`'s own decisions record.
- The product shell and visual-identity instance — lives in the consumer product's own decisions record, not this OSS boundary lib.
- The full chunk table and Vite plugin path-rewriting detail — see REF-MDL-019-01.

## Links

- [REF-MDL-019-01 — AMD Chunk Table & Vite Plugin Detail](../ref/REF-MDL-019-01-frontend-build-detail.md)
- [MDL-018 — Moodle Navigation Integration](./MDL-018-moodle-navigation-integration.md)
