---
id: MDL-016
title: 'Typed Definitions Generate Moodle Statics; Settings Are the Documented Exception'
status: accepted
date: 2026-04-03
lang: en
domains: [moodle, codegen]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-002, MDL-007, MDL-008, MDL-011]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/statics-generation]
decision: 'Typed definition objects — one per kind of Moodle static (cache, capability, service, message, hook, event, file_area) — are declared via matching extension-interface methods and generated through a single CLI entry point (`build.php --statics`); settings use the same typed-definition pattern but resolve at runtime via settings_resolver instead of being generated.'
---

# MDL-016: Typed Definitions Generate Moodle Statics; Settings Are the Documented Exception

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-305`, decided 2026-04-03, updated 2026-04-20 for CLI entry-point consolidation, with `REF-305-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle expects a family of generated static artifacts per plugin (capabilities, services, events, message providers, hooks, caches, file areas). Hand-maintaining each of these per extension, the same problem [MDL-007](./MDL-007-external-services-mobile-api.md) solved for `db/services.php` specifically, generalizes across every one of these static-generation targets.

## Considered Options

1. Continue hand-maintaining each generated static artifact (capabilities, services, events, message providers, hooks, caches, file areas) per extension — rejected: the same problem [MDL-007](./MDL-007-external-services-mobile-api.md) already solved once for `db/services.php` specifically would repeat, unaddressed, across every other static-generation target.
2. Keep one generation script per static-generation target, as the legacy plugin did (`build_tasks.php`, `build_events.php`, …) — rejected as of the 2026-04-20 update (`ADR-930`); replaced by a single consolidated CLI entry point, with the legacy scripts removed and no backward-compatibility shim.
3. Generate settings the same way as every other static, through the CLI pipeline — rejected; settings keep the same typed-definition *pattern* but resolve at runtime via `settings_resolver` instead of being generated (see [MDL-011](./MDL-011-admin-settings-declaration-lifecycle.md)) — the one documented exception.
4. Bring `db/install.xml`, `db/upgrade*.php`, `db/install.php`, and `lang/` into the generation pipeline — rejected; these remain manual, with install/upgrade extension hooks (`extend_local_middag_install_before`/`_after`) as the escape valve.
5. **Typed definition objects, one per kind of Moodle static, declared via matching extension-interface methods and generated through a single CLI entry point** ← chosen; see Decision.

## Decision

Typed definition objects (`@api`), one per kind of Moodle static (`cache`, `capability`, `service`, `message`, `hook`, `event`, `file_area`), live in the boundary's `definition/` subdirectory ([MDL-002](./MDL-002-boundary-internal-organization.md)). Extensions declare their statics via matching methods on the extension interface (`get_cache_definitions()`, `get_capabilities()`, etc.). Tasks support two mechanisms: the `#[schedule]` attribute (preferred, inline) and `get_task_definitions()` (compat). A single CLI entry point drives generation: `php cli/build.php --statics [target] [--basedir=.] [--dry-run]` — consolidated (as of the 2026-04-20 update, per `ADR-930`) from separate legacy scripts (`build_tasks.php`, `build_events.php`), removed without backward compatibility. Capability and file-area naming share one convention: the `core` extension has no prefix (`local/middag:manage`); other extensions prefix with `{extension_slug}_` (`local/middag:ecommerce_manage`). All generated files are ordered alphabetically, for predictable diffs. **Settings are the one documented exception**: they use the same typed-definition pattern but resolve at runtime via `settings_resolver` rather than being generated — see [MDL-011](./MDL-011-admin-settings-declaration-lifecycle.md). `db/install.xml`, `db/upgrade*.php`, `db/install.php`, and `lang/` also stay out of the generation pipeline, remaining manual, with install/upgrade extension hooks (`extend_local_middag_install_before`/`_after`). Full CLI usage, the naming worked examples, and the pipeline-exclusions detail are in the extracted reference doc (see Enforcement).

## Consequences

- One typed-definition pattern and one CLI entry point cover capabilities, services, events, messages, hooks, caches, and file areas — a contributor learns the pattern once.
- Alphabetical ordering in generated output makes diffs reviewable — a generated file changing for an unrelated reason is easy to spot.
- The settings exception (runtime resolution, not generation) means "typed definition" does not uniformly imply "generated file" across the whole family — a nuance a new contributor must learn explicitly, which the extracted reference doc (see Enforcement) exists partly to make unmissable. The runtime-resolution mechanism itself is out of scope here — see [MDL-011](./MDL-011-admin-settings-declaration-lifecycle.md).
- The 2026-04-20 CLI consolidation removed two legacy scripts without backward compatibility — anyone with tooling calling `build_tasks.php`/`build_events.php` directly needed to migrate at that point.
- Two static-generation targets get their own dedicated ADRs, out of scope here, due to non-trivial integration: [MDL-007](./MDL-007-external-services-mobile-api.md) for services, [MDL-008](./MDL-008-file-areas-pluginfile-routing.md) for file areas.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Typed definition objects, one per static kind, declared via matching extension-interface methods | No coded rule yet — judgment call at review time | planned |
| Single CLI entry point (`build.php --statics`) drives all generation; legacy per-target scripts are gone | No automated guard against reintroducing a second entry point — enforced only by the 2026-04-20 removal, not by an ongoing check | planned |
| Generated files are ordered alphabetically | No coded rule yet | planned |
| Capability/file-area naming convention (`core` → no prefix, others → `{extension_slug}_`) | No coded rule yet | planned |
| `db/install.xml`, `db/upgrade*.php`, `db/install.php`, `lang/` stay out of the generation pipeline | No coded rule yet — nothing prevents a contributor from routing one of these into the pipeline | planned |
| Settings excluded from generation, resolved at runtime instead | See [MDL-011](./MDL-011-admin-settings-declaration-lifecycle.md)'s own enforcement — not duplicated here | n/a |
| CLI usage detail, naming worked examples, and pipeline-exclusions detail | doc `framework/reference/adapters/moodle/statics-generation` | coded |
