---
id: MDL-003
title: 'Support Layer Pattern: One Stateless Facade per Moodle Subsystem'
status: accepted
date: 2026-04-04
domains: [moodle, boundary]
related: [MDL-001, MDL-002]
supersedes: []
superseded_by: null
lang: en
---

# MDL-003: Support Layer Pattern — One Stateless Facade per Moodle Subsystem

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-203`, decided 2026-04-04, with `ref-203-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

The boundary ([MDL-001](./MDL-001-boundary-consolidation-whitelist.md)) needed a concrete, repeatable shape for how a Moodle subsystem gets wrapped, so that every wrapper reads the same way regardless of which Moodle API sits behind it, and so untyped `stdClass`/`false` results from Moodle never leak past the boundary.

## Decision

Each consumed Moodle subsystem is encapsulated by one `*_support` class, with strict rules: one class per subsystem (e.g. `db_support`, `cache_support`, `context_support`); static, stateless methods only; no business logic — support classes translate interface, they do not decide behavior; typed return values, converting Moodle's `false|stdClass` into the framework's `?Type`. Support classes sit at the **low** level of the boundary; when a subsystem needs an OO contract for DI, a dedicated adapter delegates to the matching `*_support`. A new support class is admitted only with 2+ real call sites in the framework consuming the same subsystem (single-call convenience methods are accepted for testability when no type transformation is involved). Support classes depend on nothing but `shared/`-equivalent code — the one documented exception is the router bridge support, which must reach the kernel router for URL generation. Return semantics: lookups where "not found" is a legitimate flow return `?Type`; operations where absence signals an invalid state may expose an exception-throwing variant, or both when it makes sense. **Facade consumption is mandatory** in the legacy plugin shape: every support has a mirror facade, auto-generated via `build:facades`; importing the `support/` namespace directly is an anti-pattern. **Auth/Capability get a DI-preferential rule**: `auth_support`/`capability_support` follow the `*_support` pattern, but controllers, services, and extensions should consume them via `authentication_interface`/`capability_interface`/`authorizer_interface` (DI) — testable and decoupled. The facade fallback exists only for procedural contexts where DI is unavailable (`lib.php`, navigation hooks, install/upgrade).

## Consequences

- Every Moodle subsystem wrapper is recognizable at a glance: static, stateless, typed, no business logic — a reviewer does not need subsystem-specific knowledge to spot a violation.
- The 2+ call-site admission rule keeps the support layer from growing a class per single incidental call, at the cost of occasionally routing a one-off call through a slightly heavier path.
- The DI-preferential rule for auth/capability means two valid consumption paths coexist for the same subsystem (DI vs facade fallback) — accepted because procedural Moodle entry points genuinely cannot receive DI.
- The full 40-class inventory, the facade-consumption anti-pattern set, and the auth/capability dual-path detail live in REF-MDL-003-01, not here.

## Out of scope

- Which Moodle subsystems exist and how many classes cover them today — see REF-MDL-003-01.
- The boundary whitelist and directory taxonomy that this pattern lives inside — see [MDL-001](./MDL-001-boundary-consolidation-whitelist.md) and [MDL-002](./MDL-002-boundary-internal-organization.md).

## Links

- [REF-MDL-003-01 — Support Class Inventory & Anti-Patterns](../ref/REF-MDL-003-01-support-class-inventory.md)
- [MDL-001 — Consolidate the Moodle Boundary Behind a Physical Whitelist](./MDL-001-boundary-consolidation-whitelist.md)
- [MDL-002 — Boundary Internal Organization by Technical Type](./MDL-002-boundary-internal-organization.md)
- [CLAUDE.md](../../CLAUDE.md) — current implementation map (45 `*Support` wrappers today, `Support/Moodle` static aggregator)
