---
id: MDL-008
title: 'File Areas: Registry-Driven Pluginfile Routing'
status: accepted
date: 2026-04-04
lang: en
domains: [moodle, http, files]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-001, MDL-003, MDL-007, MDL-016]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/file-areas-pluginfile-routing]
decision: 'File areas are declared via a typed `file_area` definition and resolved at request time by `local_middag_pluginfile()` through a registry and `file_area_handler_interface`, replacing the hardcoded switch/case; the routing itself stays ungenerated, unlike `db/services.php` (MDL-007).'
---

# MDL-008: File Areas — Registry-Driven Pluginfile Routing

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-307`, decided 2026-04-04, with `ref-307-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

`local_middag_pluginfile()` as a hardcoded switch/case does not scale past a handful of file areas, and it puts authorization and delivery logic in a single procedural function with no per-area extension point — every new file area meant editing that one function.

## Considered Options

1. **Keep `local_middag_pluginfile()` as a hardcoded switch/case** — rejected: does not scale past a handful of file areas, and combines authorization and delivery logic in a single procedural function with no per-area extension point.
2. **Generate file-area routing/resolution at build time**, the same static-generation pipeline (`build_statics`) that generates `db/services.php` ([MDL-007](./MDL-007-external-services-mobile-api.md)) — rejected for routing specifically: the registry lookup instead happens at request time, every time, an accepted cost for not needing a build step on this particular hot path.
3. **Registry-driven runtime resolution** — a typed `file_area` definition plus a `file_area_handler_interface` handler contract, consulted by `local_middag_pluginfile()` at request time — chosen; see Decision.

## Decision

A new typed definition, `file_area` (`name`, `description`, `context_level`, `handler`, `supports_preview`), lets extensions declare file areas via `get_file_area_definitions()`. Naming follows the same convention as capabilities ([MDL-016](./MDL-016-moodle-statics-generation.md) §naming): the `core` extension has no prefix (`attachments`), other internal extensions use `{slug}_{area}` (e.g. `customdocs_templates`), external plugins use `{plugin}_{slug}_{area}` (e.g. `middagpro_premium_reports_exports`). The handler contract, `file_area_handler_interface` (Group A — stable public API), exposes `can_access(context, filearea, itemid, filepath, filename): bool` and `serve(stored_file, forcedownload): void`; when a definition does not declare a handler, the framework resolves `default_file_area_handler`. `local_middag_pluginfile()` stops being a hardcoded switch and instead **consults the registry**: it identifies the owning extension, resolves the handler via the container, and delegates to `file_area_handler_interface::serve()`. External plugins register additional file areas via the `extend_local_middag_file_areas` hook. `build_statics:fileareas` may generate documentation/validation, but routing and runtime resolution are **not generated** — unlike `db/services.php` ([MDL-007](./MDL-007-external-services-mobile-api.md)). The full request flow, the `file_support` method inventory, and the file-area anti-pattern list live in `framework/reference/adapters/moodle/file-areas-pluginfile-routing` (in `docs-middag-dev`).

## Consequences

- Adding a file area is a declaration (`get_file_area_definitions()`), not an edit to a shared switch statement — extensions stay isolated from each other.
- Authorization (`can_access`) and delivery (`serve`) are explicit, separately-testable handler methods instead of being implicit in a procedural function's control flow.
- Routing staying ungenerated (unlike `services.php`) means the registry lookup happens at request time, every time — an accepted cost for not needing a build step on the hot path.
- This ADR fixes the file-area registry/handler mechanism itself, not the general static-generation pipeline it explicitly opts routing out of — that pipeline's own scope and rationale belong to [MDL-016](./MDL-016-moodle-statics-generation.md).

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| File areas are declared via typed `file_area` definitions and resolved by `local_middag_pluginfile()` through the registry + `file_area_handler_interface`, never a hardcoded switch/case | No automated check — reviewed at PR time | **planned** |
| Full request flow, `file_support` method inventory, and file-area anti-patterns | doc `framework/reference/adapters/moodle/file-areas-pluginfile-routing` | **coded** |
| File-area routing/resolution stays ungenerated at build time (opts out of the `build_statics` pipeline that generates `db/services.php`, [MDL-007](./MDL-007-external-services-mobile-api.md)) | No automated check — architectural choice | **planned** |
