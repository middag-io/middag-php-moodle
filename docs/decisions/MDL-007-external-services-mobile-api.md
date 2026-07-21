---
id: MDL-007
title: 'External Services Are Generated, Not Hand-Routed Through external.php'
status: accepted
date: 2026-04-04
lang: en
domains: [moodle, http]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-005, MDL-016]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/external-services-migration]
decision: 'External Functions are exposed through a generated db/services.php (never hand-edited), with extensions declaring functions via get_service_definitions() and external.php kept as inert plugin-API scaffolding, never a router.'
---

# MDL-007: External Services Are Generated, Not Hand-Routed Through `external.php`

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-306`, decided 2026-04-04, with `ref-306-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle's External Functions API (`db/services.php`) is the native mechanism for web-service and mobile-app operations. Hand-maintaining `db/services.php` for every extension invites drift between what an extension declares and what Moodle actually registers, and gives `external.php` an implicit routing role it was never meant to carry.

## Considered Options

1. **Hand-maintain `db/services.php` per extension** — rejected: invites drift between what an extension declares and what Moodle actually registers.
2. **Let `external.php` orchestrate/route extensions' external functions** — rejected: `external.php` exists only because Moodle's plugin API requires it; using it as a dispatcher is the exact anti-pattern this decision avoids.
3. **Generate `db/services.php` from typed `get_service_definitions()` declarations via the static-generation pipeline** ([MDL-016](./MDL-016-moodle-statics-generation.md)), with Moodle resolving directly to a per-extension `{slug}_external` class ← chosen.

## Decision

The framework exposes Moodle External Functions via `db/services.php`, **generated** by the static-generation pipeline ([MDL-016](./MDL-016-moodle-statics-generation.md)) — never edited by hand. Extensions declare functions via `get_service_definitions()`, using the typed `service` definition (`name`, `classname`, `type` [`read`/`write`], `method`, `description`, `ajax`, `services`, `min_moodle`, `max_moodle`). Naming/path convention: a dedicated class per extension, `{slug}_external`, at the canonical path `classes/extensions/{slug}/{slug}_external.php`. `external.php` at the plugin root exists only because Moodle's plugin API expects it — it does **not** participate in the canonical flow and must never orchestrate or route extensions' external functions; it is auxiliary boundary scaffolding, nothing more. Flow: the extension declares via `get_service_definitions()`; the static pipeline generates `db/services.php` pointing `classname` at `{slug}_external`; Moodle resolves and invokes that class directly, with no indirection through `external.php`. A legacy population of externals predates this convention and is being migrated per-extension — see `framework/reference/adapters/moodle/external-services-migration` (in `docs-middag-dev`) for current migration status, step-by-step migration mechanics, and mobile-API/anti-pattern detail.

## Consequences

- `db/services.php` is always a faithful, generated reflection of what extensions declare — there is no manual-edit drift to reconcile.
- `external.php`'s auxiliary-only role is explicit, which prevents the common Moodle-plugin anti-pattern of treating it as a dispatcher.
- A legacy population of externals predates this convention and has not fully migrated — tracked as active technical debt, not just history (see the reference doc above for current per-extension status and migration steps).
- The static-generation pipeline itself that produces `db/services.php` is a separate decision — see [MDL-016](./MDL-016-moodle-statics-generation.md).
- The API coverage registry ([MDL-005](./MDL-005-api-coverage-registry.md)) already classifies "External Services / Web Services" as Tier A (A13) boundary work, with its artifact living in the boundary's `definition/` subdirectory — consistent with this ADR's `moodle` (not `core`) domain classification.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Legacy-to-new-style migration mechanics, current per-extension migration status, mobile-API service-array wiring, and external-function anti-patterns | doc `framework/reference/adapters/moodle/external-services-migration` | **coded** |
| `db/services.php` must never be hand-edited; extensions declare only via `get_service_definitions()` | no automated check | **planned** |
