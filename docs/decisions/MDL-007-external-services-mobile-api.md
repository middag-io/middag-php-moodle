---
id: MDL-007
title: 'External Services Are Generated, Not Hand-Routed Through external.php'
status: accepted
date: 2026-04-04
domains: [moodle, http]
related: [MDL-005, MDL-016]
supersedes: []
superseded_by: null
lang: en
---

# MDL-007: External Services Are Generated, Not Hand-Routed Through `external.php`

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-306`, decided 2026-04-04, with `ref-306-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle's External Functions API (`db/services.php`) is the native mechanism for web-service and mobile-app operations. Hand-maintaining `db/services.php` for every extension invites drift between what an extension declares and what Moodle actually registers, and gives `external.php` an implicit routing role it was never meant to carry.

## Decision

The framework exposes Moodle External Functions via `db/services.php`, **generated** by the static-generation pipeline ([MDL-016](./MDL-016-moodle-statics-generation.md)) — never edited by hand. Extensions declare functions via `get_service_definitions()`, using the typed `service` definition (`name`, `classname`, `type` [`read`/`write`], `method`, `description`, `ajax`, `services`, `min_moodle`, `max_moodle`). Naming/path convention: a dedicated class per extension, `{slug}_external`, at the canonical path `classes/extensions/{slug}/{slug}_external.php`. `external.php` at the plugin root exists only because Moodle's plugin API expects it — it does **not** participate in the canonical flow and must never orchestrate or route extensions' external functions; it is auxiliary boundary scaffolding, nothing more. Flow: the extension declares via `get_service_definitions()`; the static pipeline generates `db/services.php` pointing `classname` at `{slug}_external`; Moodle resolves and invokes that class directly, with no indirection through `external.php`.

## Consequences

- `db/services.php` is always a faithful, generated reflection of what extensions declare — there is no manual-edit drift to reconcile.
- `external.php`'s auxiliary-only role is explicit, which prevents the common Moodle-plugin anti-pattern of treating it as a dispatcher.
- A legacy population of externals predates this convention and has not fully migrated — tracked as active technical debt, not just history (see REF-MDL-007-01).

## Out of scope

- The legacy-to-new-style migration mechanics and current per-extension migration status — see REF-MDL-007-01.
- Mobile API service-array wiring detail and the external-function anti-pattern list — see REF-MDL-007-01.
- The static-generation pipeline itself that produces `db/services.php` — see [MDL-016](./MDL-016-moodle-statics-generation.md).

## Links

- [REF-MDL-007-01 — Legacy Migration Status & Mobile API Detail](../ref/REF-MDL-007-01-external-services-migration.md)
- [MDL-005 — API Coverage Registry / Tier Model](./MDL-005-api-coverage-registry.md)
- [MDL-016 — Moodle Statics Generation](./MDL-016-moodle-statics-generation.md)
