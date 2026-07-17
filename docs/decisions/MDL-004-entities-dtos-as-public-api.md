---
id: MDL-004
title: 'Entities and DTOs Are Public API by Default'
status: accepted
date: 2026-03-29
domains: [moodle, api]
related: [MDL-001, MDL-005]
supersedes: []
superseded_by: null
lang: en
---

# MDL-004: Entities and DTOs Are Public API by Default

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-204`, decided 2026-03-29, no REF companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

A generic default classifies boundary code as internal unless proven otherwise. Applying that default uniformly to the `entity/`/`dto/` subdirectories would create a boundary that does not match real usage: extensions and external plugins consume these typed wrappers directly, so treating them as internal-by-default would be fiction, not protection.

## Decision

`framework/moodle/entity/` and `framework/moodle/dto/` (in this adapter: `Domain/*` entities and DTOs, plus `AbstractMoodleEntity`) belong to **Group A — stable public API** by default, not to the generic "internal unless marked" default that applies to an arbitrary framework directory. Practical consequence: entities and DTOs enter the API evolution/deprecation cycle automatically, unless an explicit `@internal` annotation on the file opts a specific class out (recorded in the API inventory, out of scope here). Known exceptions — internal-infrastructure DTOs with no extension consumers, marked `@internal` — are the adhoc/running/scheduled task DTOs and the plugin DTO; calendar-event and notification DTOs are confirmed `@api`.

## Consequences

- Extensions and external plugins get a stable, versioned contract for the typed Moodle wrappers they already depend on, instead of a fiction that would be violated on day one.
- A small number of infrastructure-only DTOs need an explicit `@internal` opt-out — a manageable exception list rather than the default case.
- Any future breaking change to an `@api` entity/DTO must go through the deprecation cycle, not a silent rename.

## Out of scope

- The full API inventory and the tier model that classifies every boundary artifact (`@api`/`@internal`, Tier A/B/C) — see [MDL-005](./MDL-005-api-coverage-registry.md).
- The boundary whitelist that determines who may touch Moodle APIs in the first place — see [MDL-001](./MDL-001-boundary-consolidation-whitelist.md).

## Links

- [MDL-001 — Consolidate the Moodle Boundary Behind a Physical Whitelist](./MDL-001-boundary-consolidation-whitelist.md)
- [MDL-005 — API Coverage Registry / Tier Model](./MDL-005-api-coverage-registry.md)
- [CLAUDE.md](../../CLAUDE.md) — current implementation map (`Domain/` — 16 host capability areas)
