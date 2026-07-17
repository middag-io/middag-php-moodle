---
id: MDL-001
title: 'Consolidate the Moodle Boundary Behind a Physical Whitelist'
status: accepted
date: 2026-03-29
domains: [moodle, boundary]
related: [MDL-002, MDL-003, MDL-004, MDL-005]
supersedes: []
superseded_by: null
lang: en
---

# MDL-001: Consolidate the Moodle Boundary Behind a Physical Whitelist

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-201`, decided 2026-03-29). This is an archaeology pass, not a new decision — dates and rationale are historical. Per-API whitelist detail comes from the companion `ref-201-01`.

## Context

Code that depends on Moodle without an explicit physical boundary made the coupling hard to audit and weakened the notion of a boundary with the external platform (the LMS itself). Nothing stopped domain, service, or extension code from reaching into Moodle globals or core classes directly, so the real surface of platform coupling could only be discovered by reading everything.

## Decision

All code encapsulating Moodle APIs, objects, and conventions is physically consolidated behind the boundary (this adapter package's `src/` root: `Support/`, `Domain/`, `Definition/`, `Settings/`, `Adapter`-equivalent classes, etc.). No other layer — domain, services, extensions — calls native Moodle APIs directly. Access follows a **whitelist model**: only classes inside the boundary subdirectories (adapter, support, entity, dto, enum, definition, settings, contract) may touch native Moodle APIs. A boundary violation is defined precisely: any import of a Moodle namespace/type that does not go through a boundary adapter, including direct use of `\core_*`, `\mod_*`, `\local_*` (other plugins), and `stdClass` returned by Moodle APIs used outside the boundary. Pure PHP functions (`intval`, `array_map`) do not count. Controlled exceptions exist only where Moodle's plugin API mandates a file outside the boundary (`lib.php`, `db/install.php`, `db/upgrade.php`, `settings.php`, `external.php`, `classes/event|task|privacy/*`) — these may touch Moodle directly but must stay thin adapters/callbacks, never carrying business logic. All user-facing text resolves through `lang/` plus `lang_support`/`get_string()`; hardcoded PHP strings are reserved for technical messages (debug, `error_log`). Enforcement is three complementary tools: **deptrac** (a `MoodleInternal` layer collects boundary classes excluding `@api`, so only authorized layers may depend on it), **PHPStan** (`NoInternalImportsFromExtensionsRule` blocks `@internal` imports from extensions, and separately catches Moodle globals — `$DB`, `$CFG`, `$PAGE` — used without an explicit import), and **Rector** (`withSkip([...])` exceptions naming specific boundary files, e.g. `db_support.php`). deptrac and PHPStan are complementary, not redundant: one detects by class import, the other by global-variable usage. `composer check:deptrac` enforces zero violations as part of the quality pipeline.

## Consequences

- Coupling to the LMS is auditable mechanically — a violation is a whitelist violation, not a judgment call made file by file.
- Two independent static-analysis tools catch two different leak shapes (import-based and global-based); neither alone would be sufficient.
- The controlled exceptions carry an explicit obligation (thin adapter/callback only, no business logic) instead of being tracked as an informal convention.

## Out of scope

- How the boundary is organized internally by technical type (adapter/support/entity/dto/…) — see [MDL-002](./MDL-002-boundary-internal-organization.md).
- The internal shape of the `*_support` class pattern itself — see [MDL-003](./MDL-003-support-layer-pattern.md).
- Per-API whitelist detail, anti-patterns, and the exceptions justification table — see REF-MDL-001-01.

## Links

- [REF-MDL-001-01 — Boundary Whitelist Detail](../ref/REF-MDL-001-01-boundary-whitelist-detail.md)
- [MDL-002 — Boundary Internal Organization by Technical Type](./MDL-002-boundary-internal-organization.md)
- [MDL-003 — Support Layer Pattern](./MDL-003-support-layer-pattern.md)
- [CLAUDE.md](../../CLAUDE.md) — current implementation map
