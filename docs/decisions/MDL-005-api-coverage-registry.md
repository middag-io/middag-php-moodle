---
id: MDL-005
title: 'API Coverage Registry: a 3-Tier Classification Model'
status: accepted
date: 2026-04-07
domains: [moodle, api]
related: [MDL-001, MDL-004, MDL-006, MDL-017]
supersedes: []
superseded_by: null
lang: en
---

# MDL-005: API Coverage Registry — a 3-Tier Classification Model

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-205`, decided 2026-04-07, with `ref-205-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

By the time the boundary held dozens of wrapped Moodle subsystems ([MDL-001](./MDL-001-boundary-consolidation-whitelist.md), [MDL-003](./MDL-003-support-layer-pattern.md)), there was no single registry answering "is this Moodle API covered, and how thoroughly, and why." Coverage decisions were being made ad hoc per subsystem, with no shared vocabulary for "fully integrated" versus "just enough to not break" versus "deliberately not our problem."

## Decision

Every Moodle API the boundary might touch is classified into one of three tiers, each with standardized justification language ("Essential to the framework: {reason}" / "Protection interface: {reason}. Priority: {high|medium|low}" / "Out of scope: {reason}"):

| Tier | Name                    | Pattern                                                              | When to use                                                                |
|------|-------------------------|----------------------------------------------------------------------|----------------------------------------------------------------------------|
| A    | Full Integration        | `*_support`, entities, DTOs, enums, definitions, adapters, contracts | API consumed by 2+ extensions or a cross-cutting concern                   |
| B    | Compatibility Interface | Minimal contract or light support                                    | API consumed by specific extensions; absorbs breaking changes              |
| C    | Out of Scope            | No artifact                                                          | Does not apply to the framework's purpose, or covered by its own mechanism |

Admission to Tier A requires 2+ real call sites, a support class following the static-stateless pattern ([MDL-003](./MDL-003-support-layer-pattern.md)), entities/DTOs following [MDL-002](./MDL-002-boundary-internal-organization.md)/[MDL-004](./MDL-004-entities-dtos-as-public-api.md), an updated boundary whitelist ([MDL-001](./MDL-001-boundary-consolidation-whitelist.md)), and — when the integration is non-trivial (DI, Routing) — a complementary ADR. Promotion from B to A happens once a Tier B interface accumulates 3+ distinct operations or 2+ consuming extensions. The registry is reviewed **by event** (a new Moodle major version, a new extension, a product-discovery gap), not on a calendar.

## Consequences

- Every Moodle API has one unambiguous answer to "how covered is this and why" instead of an implicit, per-subsystem judgment call.
- Tier A's 2+ call-site rule keeps the registry from inflating with speculative coverage, at the cost of a brief B-tier stop for genuinely new integrations.
- Version-conditional availability (Hooks, Check, Routing, DI all gate on a Moodle minimum version) is a first-class part of the registry, not an afterthought — see REF-MDL-005-01 for the guard table.

## Out of scope

- The full 45-entry Tier A registry, the version-guard table, the `@api`/`@internal` per-artifact classification, and the 3 Tier C entries — see REF-MDL-005-01.
- The DI and Routing integrations that Tier A references as "non-trivial, needs a complementary ADR" — see [MDL-006](./MDL-006-di-bridge-container-interoperability.md) and [MDL-017](./MDL-017-routing-bridge-coexistence.md).

## Links

- [REF-MDL-005-01 — Tier A Registry, Version Guards & Tier C](../ref/REF-MDL-005-01-api-coverage-inventory.md)
- [MDL-001 — Consolidate the Moodle Boundary Behind a Physical Whitelist](./MDL-001-boundary-consolidation-whitelist.md)
- [MDL-004 — Entities and DTOs Are Public API by Default](./MDL-004-entities-dtos-as-public-api.md)
- [MDL-006 — DI Bridge / Container Interoperability](./MDL-006-di-bridge-container-interoperability.md)
- [MDL-017 — Routing Bridge Coexistence with the Native Moodle Router](./MDL-017-routing-bridge-coexistence.md)
