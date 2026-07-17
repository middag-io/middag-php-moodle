---
ref: REF-MDL-016-01
adr: MDL-016
title: 'CLI Usage, Naming Detail & Exclusions'
lang: en
---

# REF-MDL-016-01: CLI Usage, Naming Detail & Exclusions

> Detail supporting [MDL-016](../decisions/MDL-016-moodle-statics-generation.md). Reconstructed from the `moodle-local_middag` legacy vault (`ADR-305`, `REF-305-01`).

## CLI entry point

```
php cli/build.php --statics [target] [--basedir=.] [--dry-run]
```

Consolidated per ADR-930 (2026-04-20 update), absorbing the older `build_tasks.php` and `build_events.php` scripts, which were removed with no backward-compatibility shim.

## Task declaration, two mechanisms

- `#[schedule]` attribute — preferred, inline on the task class.
- `get_task_definitions()` — compatibility path for cases the attribute does not fit.

## Naming convention worked examples

- Capability: `core` extension → `local/middag:manage` (no prefix); other extensions → `local/middag:ecommerce_manage` (`{extension_slug}_` prefix).
- File area: same convention as capabilities (see [MDL-008](../decisions/MDL-008-file-areas-pluginfile-routing.md)).

## Ordering

All generated files are alphabetically ordered — a deliberate choice for predictable, reviewable diffs.

## Exclusions from the generation pipeline

`db/install.xml`, `db/upgrade*.php`, `db/install.php`, `lang/` all remain manual. Extension hooks exist for install/upgrade: `extend_local_middag_install_before`/`extend_local_middag_install_after`.

## State check — ADR vs. REF alignment

Unlike [MDL-014](../decisions/MDL-014-muc-cache-decorator.md)'s cache-decorator contradiction, the ADR and REF here are aligned: the only real change of state is the CLI entry-point consolidation into `build.php`, which both documents reflect consistently. Not a contradiction requiring escalation.
