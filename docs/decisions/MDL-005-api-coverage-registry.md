---
id: MDL-005
title: 'API Coverage Registry: a 3-Tier Classification Model'
status: accepted
date: 2026-04-07
lang: en
domains: [moodle, api]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-001, MDL-004, MDL-006, MDL-017]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/api-coverage-inventory]
decision: 'Every Moodle API the boundary might touch is classified into one of three coverage tiers — A (Full Integration, 2+ call sites), B (Compatibility Interface), C (Out of Scope) — each with standardized justification language, explicit admission/promotion criteria, and event-triggered (not calendar-based) registry review.'
---

# MDL-005: API Coverage Registry — a 3-Tier Classification Model

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-205`, decided 2026-04-07, with `ref-205-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

By the time the boundary held dozens of wrapped Moodle subsystems ([MDL-001](./MDL-001-boundary-consolidation-whitelist.md), [MDL-003](./MDL-003-support-layer-pattern.md)), there was no single registry answering "is this Moodle API covered, and how thoroughly, and why." Coverage decisions were being made ad hoc per subsystem, with no shared vocabulary for "fully integrated" versus "just enough to not break" versus "deliberately not our problem."

## Considered Options

1. **Keep coverage decisions ad hoc, per subsystem, with no shared registry or vocabulary** (the status quo described in Context) — rejected: no single source of truth for "is this API covered and why," and each subsystem re-derives its own informal notion of "fully integrated" vs. "just enough" vs. "not our problem."
2. **Admit any candidate API directly to Tier A (Full Integration) with no call-site threshold or probation stop** — rejected: the Decision explicitly trades this off ("Tier A's 2+ call-site rule keeps the registry from inflating with speculative coverage, at the cost of a brief B-tier stop for genuinely new integrations"), i.e., unrestricted direct-to-A admission was considered and rejected in favor of a 2+ call-site gate with a Tier B waiting stage.
3. **Review the registry on a fixed calendar cadence (e.g., quarterly)** — rejected in favor of event-triggered review only ("reviewed by event ..., not on a calendar").

## Decision

Every Moodle API the boundary might touch is classified into one of three tiers, each with standardized justification language ("Essential to the framework: {reason}" / "Protection interface: {reason}. Priority: {high|medium|low}" / "Out of scope: {reason}"):

| Tier | Name                    | Pattern                                                              | When to use                                                                |
|------|-------------------------|------------------------------------------------------------------------|----------------------------------------------------------------------------|
| A    | Full Integration        | `*_support`, entities, DTOs, enums, definitions, adapters, contracts | API consumed by 2+ extensions or a cross-cutting concern                   |
| B    | Compatibility Interface | Minimal contract or light support                                    | API consumed by specific extensions; absorbs breaking changes              |
| C    | Out of Scope            | No artifact                                                          | Does not apply to the framework's purpose, or covered by its own mechanism |

Admission to Tier A requires 2+ real call sites, a support class following the static-stateless pattern ([MDL-003](./MDL-003-support-layer-pattern.md)), entities/DTOs following [MDL-002](./MDL-002-boundary-internal-organization.md)/[MDL-004](./MDL-004-entities-dtos-as-public-api.md), an updated boundary whitelist ([MDL-001](./MDL-001-boundary-consolidation-whitelist.md)), and — when the integration is non-trivial (DI, Routing) — a complementary ADR. Promotion from B to A happens once a Tier B interface accumulates 3+ distinct operations or 2+ consuming extensions. The registry is reviewed **by event** (a new Moodle major version, a new extension, a product-discovery gap), not on a calendar.

## Consequences

- Every Moodle API has one unambiguous answer to "how covered is this and why" instead of an implicit, per-subsystem judgment call.
- Tier A's 2+ call-site rule keeps the registry from inflating with speculative coverage, at the cost of a brief B-tier stop for genuinely new integrations.
- Version-conditional availability (Hooks, Check, Routing, DI all gate on a Moodle minimum version) is a first-class part of the registry, not an afterthought — the full guard table now lives in the extracted reference doc (see Enforcement).
- The DI and Routing integrations that Tier A flags as "non-trivial, needs a complementary ADR" are each covered by their own decision: [MDL-006](./MDL-006-di-bridge-container-interoperability.md) (DI bridge / container interoperability) and [MDL-017](./MDL-017-routing-bridge-coexistence.md) (routing bridge coexistence with the native Moodle router).

## Enforcement

| Decision clause                                                                                                     | Verification                                                                                    | State   |
|-----------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------|---------|
| Full inventory: 45 Tier A entries, version-guard table, `@api`/`@internal` classification, 3 Tier C entries          | Reference doc — `framework/reference/adapters/moodle/api-coverage-inventory`                     | coded   |
| Tier A admission requires 2+ real call sites + static-stateless support class + entity/DTO/whitelist conformance    | No automated check counts call sites or verifies promotion criteria yet                          | planned |
| `@internal` boundary (`moodle/support/*`, `moodle/adapter/*`) must not be imported directly outside a facade/DI seam | No MDGStan rule restricts imports across the `@api`/`@internal` boundary yet                      | planned |
