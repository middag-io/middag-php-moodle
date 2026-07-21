---
id: MDL-019
title: 'Frontend Moodle Integration: Theme Color Inheritance & AMD Build Output'
status: accepted
date: 2026-04-15
lang: en
domains: [moodle, frontend]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-018]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/frontend-build-detail]
decision: 'The frontend optionally inherits the active Moodle theme''s brand color via an admin-gated CSS custom property, and compiles through a dedicated Vite plugin directly into Moodle-native AMD/RequireJS chunks with on-demand lazy loading — never a generic bundler target requiring manual repackaging.'
---

# MDL-019: Frontend Moodle Integration — Theme Color Inheritance & AMD Build Output

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-807`, decided 2026-04-15) — specifically the two Moodle-boundary fragments (§17-18) extracted from that broader frontend architecture ADR. This is an archaeology pass, not a new decision — dates and rationale are historical. ADR-807's remaining ~23 sections (page-contract/composition mechanism, product shell/visual identity) live in the `middag-io/ui` and `middag-io/core`-equivalent libraries' own decisions records, not here — split by explicit judgment call during this consolidation pass (merging what the source material treated as two adjacent subsections, since both describe how the same frontend build adapts to the Moodle host specifically, sharing one rationale).

## Context

The frontend (built in `middag-io/ui`/`middag-io/framework`, consumed here) needs two Moodle-specific adaptations that are neither generic UI mechanism nor product visual identity: reading the active Moodle theme's brand color so the product can optionally match it, and packaging its build output for Moodle's own AMD/RequireJS module-loading mechanism rather than a generic bundler target.

## Considered Options

1. **Hardcode the product's own fixed theme, with no adaptation to the host Moodle site's branding** — rejected: sites that want visual consistency with their existing brand would see a mismatched, visually isolated UI.
2. **Force theme-color inheritance unconditionally, with no opt-out** — rejected: sites that want MIDDAG's own fixed identity would have no way to disable inheritance; an admin checkbox (`local_middag/inherit_theme_colors`) was added instead.
3. **Ship a generic bundler target and manually repackage it for Moodle's AMD/RequireJS loader on every build** — rejected: a dedicated Vite plugin (`moodle-amd`) that emits AMD-native output directly, with no manual repackaging step, was built instead.
4. **Run two separate `watch` processes in dev mode** (one targeting `amd/build/`, one targeting `amd/src/`) — rejected: the plugin duplicates build output into `amd/src/` within a single watch process instead, avoiding the second process entirely.

## Decision

**Theme color inheritance**: the frontend optionally inherits the active Moodle theme's primary color, read from `$PAGE->theme->settings->brandcolor` and injected as the `--middag-brand` CSS custom property in `<head>`. Tailwind's semantic tokens (`--primary`, etc.) map onto `--middag-brand` when inheritance is enabled. An admin setting, `local_middag/inherit_theme_colors` (checkbox), controls whether MIDDAG inherits the Moodle theme or uses its own fixed theme.

**AMD build output**: the frontend compiles via Vite with direct AMD output into `amd/build/`, with `-lazy`-suffixed chunks loaded on-demand through Moodle's native `requirejs.php` mechanism. A dedicated Vite plugin (`moodle-amd`) rewrites relative paths between chunks into the `local_middag/{name}` format Moodle expects, and duplicates the output into `amd/src/` for dev mode, eliminating a duplicate `watch` process.

## Consequences

- The product can visually match the host Moodle site's brand color without hardcoding it, while still allowing sites that want MIDDAG's own fixed identity to opt out via the admin setting.
- Build output is directly consumable by Moodle's native module loader with no manual repackaging step, at the cost of a bespoke Vite plugin that must be kept in sync with Moodle's `requirejs.php` expectations.
- Lazy-loaded chunks (React vendor, UI primitives, table library, extension bundles) keep the immediately-loaded entry small, deferring the bulk of the frontend payload until it is actually needed.
- The page-contract/composition mechanism this frontend build serves, and the product shell/visual-identity instance itself, are deliberately out of scope here — they live in `middag-io/ui`'s own decisions record and in the consuming product's own decisions record, respectively, not in this OSS boundary lib.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Theme inheritance toggle (`local_middag/inherit_theme_colors`) gates whether `--middag-brand` (from `$PAGE->theme->settings->brandcolor`) drives Tailwind's semantic tokens | No automated check found | planned |
| AMD chunk table, `moodle-amd` Vite plugin path-rewriting, and dev-mode `amd/src/` duplication | Reference doc — `framework/reference/adapters/moodle/frontend-build-detail` | coded |
| `moodle-amd` rewrites relative chunk import paths into the `local_middag/{name}` format Moodle's AMD loader expects | No automated check found | planned |
