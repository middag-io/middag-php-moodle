---
id: MDL-008
title: 'File Areas: Registry-Driven Pluginfile Routing'
status: accepted
date: 2026-04-04
domains: [moodle, http, files]
related: [MDL-001, MDL-003]
supersedes: []
superseded_by: null
lang: en
---

# MDL-008: File Areas — Registry-Driven Pluginfile Routing

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-307`, decided 2026-04-04, with `ref-307-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

`local_middag_pluginfile()` as a hardcoded switch/case does not scale past a handful of file areas, and it puts authorization and delivery logic in a single procedural function with no per-area extension point — every new file area meant editing that one function.

## Decision

A new typed definition, `file_area` (`name`, `description`, `context_level`, `handler`, `supports_preview`), lets extensions declare file areas via `get_file_area_definitions()`. Naming follows the same convention as capabilities ([MDL-016](./MDL-016-moodle-statics-generation.md) §naming): the `core` extension has no prefix (`attachments`), other internal extensions use `{slug}_{area}` (e.g. `customdocs_templates`), external plugins use `{plugin}_{slug}_{area}` (e.g. `middagpro_premium_reports_exports`). The handler contract, `file_area_handler_interface` (Group A — stable public API), exposes `can_access(context, filearea, itemid, filepath, filename): bool` and `serve(stored_file, forcedownload): void`; when a definition does not declare a handler, the framework resolves `default_file_area_handler`. `local_middag_pluginfile()` stops being a hardcoded switch and instead **consults the registry**: it identifies the owning extension, resolves the handler via the container, and delegates to `file_area_handler_interface::serve()`. External plugins register additional file areas via the `extend_local_middag_file_areas` hook. `build_statics:fileareas` may generate documentation/validation, but routing and runtime resolution are **not generated** — unlike `db/services.php` ([MDL-007](./MDL-007-external-services-mobile-api.md)).

## Consequences

- Adding a file area is a declaration (`get_file_area_definitions()`), not an edit to a shared switch statement — extensions stay isolated from each other.
- Authorization (`can_access`) and delivery (`serve`) are explicit, separately-testable handler methods instead of being implicit in a procedural function's control flow.
- Routing staying ungenerated (unlike `services.php`) means the registry lookup happens at request time, every time — an accepted cost for not needing a build step on the hot path.

## Out of scope

- The full request flow diagram, `file_support`'s method inventory, and the file-area anti-pattern list — see REF-MDL-008-01.
- The general static-generation pipeline that this decision explicitly opts out of for routing — see [MDL-016](./MDL-016-moodle-statics-generation.md).

## Links

- [REF-MDL-008-01 — Pluginfile Flow, File Support Inventory & Anti-Patterns](../ref/REF-MDL-008-01-file-areas-detail.md)
- [MDL-001 — Consolidate the Moodle Boundary Behind a Physical Whitelist](./MDL-001-boundary-consolidation-whitelist.md)
- [MDL-003 — Support Layer Pattern](./MDL-003-support-layer-pattern.md)
