---
id: MDL-003
title: 'Support Layer Pattern: One Stateless Facade per Moodle Subsystem'
status: accepted
date: 2026-04-04
lang: en
domains: [moodle, boundary]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-001, MDL-002]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/support-class-inventory]
decision: 'Each Moodle subsystem is wrapped by exactly one static, stateless *_support class — typed returns, no business logic, a 2+ call-site admission threshold, shared/-only dependencies — with mandatory facade consumption in the legacy plugin shape and a DI-preferential path for auth/capability.'
---

# MDL-003: Support Layer Pattern — One Stateless Facade per Moodle Subsystem

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-203`, decided 2026-04-04, with `ref-203-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

The boundary ([MDL-001](./MDL-001-boundary-consolidation-whitelist.md)) needed a concrete, repeatable shape for how a Moodle subsystem gets wrapped, so that every wrapper reads the same way regardless of which Moodle API sits behind it, and so untyped `stdClass`/`false` results from Moodle never leak past the boundary.

## Considered Options

1. Allow direct consumption of `*_support` classes everywhere, with no facade indirection.
2. Uniform facade-only consumption for auth/capability, with no DI path for controllers/services/extensions.
3. No admission threshold — permit a new support class for a single call site.
4. Let support classes depend freely on domain/service/kernel-equivalent layers, subsystem by subsystem.
5. **One `*_support` class per subsystem — static, stateless, typed, 2+ call-site admission, `shared/`-only dependencies (router bridge excepted), mandatory facade consumption in the legacy plugin shape, DI-preferential for auth/capability** ← chosen.

## Decision

Each consumed Moodle subsystem is encapsulated by one `*_support` class, with strict rules: one class per subsystem (e.g. `db_support`, `cache_support`, `context_support`); static, stateless methods only; no business logic — support classes translate interface, they do not decide behavior; typed return values, converting Moodle's `false|stdClass` into the framework's `?Type`. Support classes sit at the **low** level of the boundary; when a subsystem needs an OO contract for DI, a dedicated adapter delegates to the matching `*_support`. A new support class is admitted only with 2+ real call sites in the framework consuming the same subsystem (single-call convenience methods are accepted for testability when no type transformation is involved). Support classes depend on nothing but `shared/`-equivalent code — the one documented exception is the router bridge support, which must reach the kernel router for URL generation. Return semantics: lookups where "not found" is a legitimate flow return `?Type`; operations where absence signals an invalid state may expose an exception-throwing variant, or both when it makes sense. **Facade consumption is mandatory** in the legacy plugin shape: every support has a mirror facade, auto-generated via `build:facades`; importing the `support/` namespace directly is an anti-pattern. **Auth/Capability get a DI-preferential rule**: `auth_support`/`capability_support` follow the `*_support` pattern, but controllers, services, and extensions should consume them via `authentication_interface`/`capability_interface`/`authorizer_interface` (DI) — testable and decoupled. The facade fallback exists only for procedural contexts where DI is unavailable (`lib.php`, navigation hooks, install/upgrade).

## Consequences

- Every Moodle subsystem wrapper is recognizable at a glance: static, stateless, typed, no business logic — a reviewer does not need subsystem-specific knowledge to spot a violation.
- The 2+ call-site admission rule keeps the support layer from growing a class per single incidental call, at the cost of occasionally routing a one-off call through a slightly heavier path.
- The DI-preferential rule for auth/capability means two valid consumption paths coexist for the same subsystem (DI vs facade fallback) — accepted because procedural Moodle entry points genuinely cannot receive DI.
- This ADR is the internal contract of the `support/` subdirectory specifically; it sits inside the wider boundary whitelist and directory-by-technical-type taxonomy set by [MDL-001](./MDL-001-boundary-consolidation-whitelist.md) and [MDL-002](./MDL-002-boundary-internal-organization.md) — neither is restated here.
- The full class inventory (45 `*Support` classes plus the `Support/Moodle` static aggregator, at last count — see `CLAUDE.md` for the live figure) and the anti-pattern catalog live in the extracted reference doc, not in this ADR (see Enforcement).

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| One `*_support` class per subsystem; static, stateless, typed returns, no business logic | no coded static-analysis rule yet (PHPStan/Rector) | **planned** |
| 2+ real call-site admission threshold for a new support class | no coded rule yet — judgment call at review time | **planned** |
| Support classes depend on nothing but `shared/`-equivalent code (router bridge support excepted) | no coded rule yet — the deptrac/PHPStan boundary checks from [MDL-001](./MDL-001-boundary-consolidation-whitelist.md) cover the whitelist as a whole, not this narrower internal-dependency restriction | **planned** |
| Auth/Capability DI-preferential rule (facade fallback reserved for procedural contexts) | no coded rule yet | **planned** |
| Support class inventory, current class count, and anti-pattern catalog | doc `framework/reference/adapters/moodle/support-class-inventory` | **coded** |
