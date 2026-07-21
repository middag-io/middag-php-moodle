---
id: MDL-001
title: 'Consolidate the Moodle Boundary Behind a Physical Whitelist'
status: accepted
date: 2026-03-29
lang: en
domains: [moodle, boundary]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-002, MDL-003, MDL-004, MDL-005]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/boundary-whitelist]
decision: 'All Moodle-API-touching code is physically confined to boundary subdirectories (adapter, support, entity, dto, enum, definition, settings, contract) under a whitelist model, with narrowly scoped, obligation-bound exceptions where Moodle''s own plugin API mandates a file outside the boundary.'
---

# MDL-001: Consolidate the Moodle Boundary Behind a Physical Whitelist

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-201`, decided 2026-03-29). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Code that depends on Moodle without an explicit physical boundary made the coupling hard to audit and weakened the notion of a boundary with the external platform (the LMS itself). Nothing stopped domain, service, or extension code from reaching into Moodle globals or core classes directly, so the real surface of platform coupling could only be discovered by reading everything.

## Considered Options

1. **Rely on convention/code review alone**, with no physical or mechanical enforcement of where Moodle-touching code may live. Rejected — the Context above is precisely this state: coupling could only be discovered by reading everything, not audited mechanically.
2. **Enforce boundary purity everywhere, with no exceptions** — including the files where Moodle's own plugin API mandates an entry point outside `src/` (`lib.php`, `db/install.php`, `db/upgrade.php`, `settings.php`, `external.php`, `classes/event|task|privacy/*`). Rejected — Moodle's plugin contract requires these files to exist at fixed locations outside the boundary; a blanket "no exceptions" rule is unworkable against a platform API this project does not control. Controlled, obligation-bound exceptions were adopted instead (thin adapter/callback only, never business logic).
3. **Use a single detection mechanism** (either import-based or global-variable-based static analysis) to catch boundary violations. Rejected — a single mechanism cannot catch both leak shapes: direct imports of Moodle namespaces/types (`\core_*`, `\mod_*`, `\local_*`, `stdClass` returned by Moodle APIs) and direct use of Moodle superglobals (`$DB`, `$CFG`, `$PAGE`) without an explicit import. Two complementary tools were adopted instead.

## Decision

All code encapsulating Moodle APIs, objects, and conventions is physically consolidated behind the boundary (this adapter package's `src/` root: `Support/`, `Domain/`, `Definition/`, `Settings/`, `Adapter`-equivalent classes, etc.). No other layer — domain, services, extensions — calls native Moodle APIs directly. Access follows a **whitelist model**: only classes inside the boundary subdirectories (adapter, support, entity, dto, enum, definition, settings, contract) may touch native Moodle APIs.

A boundary violation is defined precisely: any import of a Moodle namespace/type that does not go through a boundary adapter, including direct use of `\core_*`, `\mod_*`, `\local_*` (other plugins), and `stdClass` returned by Moodle APIs used outside the boundary. Pure PHP functions (`intval`, `array_map`) do not count.

Controlled exceptions exist only where Moodle's plugin API mandates a file outside the boundary (`lib.php`, `db/install.php`, `db/upgrade.php`, `settings.php`, `external.php`, `classes/event|task|privacy/*`) — these may touch Moodle directly but must stay thin adapters/callbacks, never carrying business logic.

All user-facing text resolves through `lang/` plus `lang_support`/`get_string()`; hardcoded PHP strings are reserved for technical messages (debug, `error_log`).

Enforcement is intended as three complementary tools: an import-based check (a dependency-boundary tool grouping boundary classes excluding `@api` into a single internal layer, so only authorized layers may depend on it), a global-variable-based check (a static-analysis rule blocking `@internal` imports from extensions, and separately catching Moodle globals — `$DB`, `$CFG`, `$PAGE` — used without an explicit import), and a refactoring-tool exception list (naming specific boundary files, e.g. `db_support.php`, that are intentionally skipped from refactoring passes that would otherwise fight the boundary's deliberately repetitive wrapper shape). The first two are complementary, not redundant: one detects by class import, the other by global-variable usage; the third protects the boundary's shape from being refactored away.

## Consequences

- Coupling to the LMS is auditable mechanically — a violation is a whitelist violation, not a judgment call made file by file, once the enforcement tooling below is in place.
- Two independent static-analysis mechanisms are meant to catch two different leak shapes (import-based and global-based); neither alone would be sufficient — see Enforcement for the current, honest state of each.
- The controlled exceptions carry an explicit obligation (thin adapter/callback only, no business logic) instead of being tracked as an informal convention.
- How the boundary is organized internally by technical type (adapter/support/entity/dto/…) is a separate decision — see [MDL-002](./MDL-002-boundary-internal-organization.md).
- The internal shape of the `*_support` class pattern itself is a separate decision — see [MDL-003](./MDL-003-support-layer-pattern.md).
- Per-API whitelist detail, anti-patterns, and the exceptions-justification table are a living inventory, not durable rule text — extracted to a standalone reference doc (see Enforcement) so this ADR does not need a revision every time a support class is added.
- The current mapping of boundary subdirectories to actual codebase contents is tracked in the project's `CLAUDE.md` (`../../CLAUDE.md`), not duplicated here.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Only boundary-subdirectory classes may import native Moodle namespaces/types (`\core_*`, `\mod_*`, `\local_*`, boundary-returned `stdClass`) | Dependency-boundary tool (e.g. deptrac), `MoodleInternal` layer collecting boundary classes excluding `@api` | planned — referenced in code comments (`NavbarService.php`) as intent; no dependency-boundary tool is installed or configured in this repo yet |
| Moodle superglobals (`$DB`, `$CFG`, `$PAGE`) may not be used outside the boundary without an explicit import | Custom PHPStan rule (e.g. `NoInternalImportsFromExtensionsRule`) | planned — no custom rule is registered in `.phpstan.neon`; only generic level-6 PHPStan runs today via `composer check:stan` |
| Boundary wrapper files are exempted from refactoring rules that would fight their deliberately repetitive shape (e.g. `db_support.php`) | Rector `withSkip([...])` naming specific boundary files | planned — `.php-rector.php` currently skips whole Rector rule classes only; no per-file (e.g. `db_support.php`) exceptions exist yet |
| Controlled exceptions (`lib.php`, `db/install.php`, `db/upgrade.php`, `settings.php`, `external.php`, `classes/event\|task\|privacy/*`) stay thin adapters/callbacks, never business logic | Code review; no automated check found | planned |
| User-facing text resolves through `lang/` + `lang_support`/`get_string()`, not hardcoded PHP strings | No automated check found | planned |
| Per-API whitelist detail (permitted calls, correct usage, anti-pattern per support/adapter class) | doc `framework/reference/adapters/moodle/boundary-whitelist` | coded |
