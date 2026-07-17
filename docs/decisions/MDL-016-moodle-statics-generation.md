---
id: MDL-016
title: 'Typed Definitions Generate Moodle Statics; Settings Are the Documented Exception'
status: accepted
date: 2026-04-03
domains: [moodle, codegen]
related: [MDL-002, MDL-007, MDL-008, MDL-011]
supersedes: []
superseded_by: null
lang: en
---

# MDL-016: Typed Definitions Generate Moodle Statics; Settings Are the Documented Exception

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-305`, decided 2026-04-03, updated 2026-04-20 for CLI entry-point consolidation, with `REF-305-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle expects a family of generated static artifacts per plugin (capabilities, services, events, message providers, hooks, caches, file areas). Hand-maintaining each of these per extension, the same problem [MDL-007](./MDL-007-external-services-mobile-api.md) solved for `db/services.php` specifically, generalizes across every one of these static-generation targets.

## Decision

Typed definition objects (`@api`), one per kind of Moodle static (`cache`, `capability`, `service`, `message`, `hook`, `event`, `file_area`), live in the boundary's `definition/` subdirectory ([MDL-002](./MDL-002-boundary-internal-organization.md)). Extensions declare their statics via matching methods on the extension interface (`get_cache_definitions()`, `get_capabilities()`, etc.). Tasks support two mechanisms: the `#[schedule]` attribute (preferred, inline) and `get_task_definitions()` (compat). A single CLI entry point drives generation: `php cli/build.php --statics [target] [--basedir=.] [--dry-run]` — consolidated (as of the 2026-04-20 update, per ADR-930) from separate legacy scripts (`build_tasks.php`, `build_events.php`, removed without backward compatibility). Capability and file-area naming share one convention: the `core` extension has no prefix (`local/middag:manage`); other extensions prefix with `{extension_slug}_` (`local/middag:ecommerce_manage`). All generated files are ordered alphabetically, for predictable diffs. **Settings are the one documented exception**: they use the same typed-definition pattern but resolve at runtime via `settings_resolver` rather than being generated — see [MDL-011](./MDL-011-admin-settings-declaration-lifecycle.md). `db/install.xml`, `db/upgrade*.php`, `db/install.php`, and `lang/` also stay out of the generation pipeline, remaining manual, with install/upgrade extension hooks (`extend_local_middag_install_before/_after`).

## Consequences

- One typed-definition pattern and one CLI entry point cover capabilities, services, events, messages, hooks, caches, and file areas — a contributor learns the pattern once.
- Alphabetical ordering in generated output makes diffs reviewable — a generated file changing for an unrelated reason is easy to spot.
- The settings exception (runtime resolution, not generation) means "typed definition" does not uniformly imply "generated file" across the whole family — a nuance a new contributor must learn explicitly, which REF-MDL-016-01 exists partly to make unmissable.
- The 2026-04-20 CLI consolidation removed two legacy scripts without backward compatibility — anyone with tooling calling `build_tasks.php`/`build_events.php` directly needed to migrate at that point.

## Out of scope

- The full CLI usage detail and the capability/file-area naming worked examples — see REF-MDL-016-01.
- The settings runtime-resolution mechanism itself — see [MDL-011](./MDL-011-admin-settings-declaration-lifecycle.md).
- The two specific static-generation targets that get their own dedicated ADRs due to non-trivial integration ([MDL-007](./MDL-007-external-services-mobile-api.md) for services, [MDL-008](./MDL-008-file-areas-pluginfile-routing.md) for file areas).

## Links

- [REF-MDL-016-01 — CLI Usage, Naming Detail & Exclusions](../ref/REF-MDL-016-01-statics-generation-detail.md)
- [MDL-002 — Boundary Internal Organization by Technical Type](./MDL-002-boundary-internal-organization.md)
- [MDL-007 — External Services & Mobile API](./MDL-007-external-services-mobile-api.md)
- [MDL-008 — File Areas & Pluginfile Routing](./MDL-008-file-areas-pluginfile-routing.md)
- [MDL-011 — Admin Settings Declaration Lifecycle](./MDL-011-admin-settings-declaration-lifecycle.md)
