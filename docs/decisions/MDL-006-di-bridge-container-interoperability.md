---
id: MDL-006
title: 'DI Bridge: Unidirectional, Curated Exposure to the Moodle Container'
status: accepted
date: 2026-04-08
domains: [moodle, di]
related: [MDL-001, MDL-005]
supersedes: []
superseded_by: null
lang: en
---

# MDL-006: DI Bridge — Unidirectional, Curated Exposure to the Moodle Container

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-207`, decided 2026-04-08, with `ref-207-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle 4.4+ introduced its own PHP-DI (PSR-11) container via `\core\di`, with autowiring and registration through the `di_configuration` hook. The framework already maintains its own container. Two containers now coexist on the same site, and without an explicit boundary rule, framework services could end up resolving through `\core\di::get()` directly — reintroducing exactly the kind of ad hoc platform coupling the boundary (MDL-001) exists to prevent.

## Decision

The two containers stay **separated**, with **selective, unidirectional** exposure — framework to Moodle DI only:

| Direction                         | Mechanism                                                                             | When to use                                           |
|-----------------------------------|---------------------------------------------------------------------------------------|-------------------------------------------------------|
| Framework -> Moodle DI (outbound) | `di_bridge_support` registers services via the `di_configuration` hook                | Expose services for external consumption              |
| Moodle DI -> Framework (inbound)  | No container bridge exists — the boundary layer (support/adapter) encapsulates access | Framework needs a Moodle service (e.g. `\core\clock`) |

**Absolute rule**: framework services never resolve via `\core\di::get()` — always through the boundary (support/adapter). There is no change to the framework's own container factory or register/boot/compile lifecycle. Outbound, `di_bridge_support::configure(\core\hook\di_configuration $hook)` exposes a **curated** list of services (`EXPORTS`) — today, only the main facade (`middag::class`, via `middag::get_instance()`). The exposure criterion is manual and deliberate: only `@api` (Group A) services with demonstrated external-consumer utility, never auto-discovery. Registration happens through the static-generation pipeline (MDL-016), gated with `min_moodle: '4.4'` so the callback never runs on older Moodle. `di_bridge_support::is_available()` performs the explicit availability check (`version_support::supports('moodle_di_hook')` plus a `class_exists()` check).

## Consequences

- External plugins on Moodle >= 4.4 can resolve a MIDDAG service through their own native DI (`\core\di::get()`), including autowiring in their own controllers, without the framework ever depending on Moodle's container internals.
- The curated, manual `EXPORTS` list keeps the exposed surface deliberately small — today a single entry — trading completeness for a surface the team can reason about fully.
- `get_extension_exports()` is reserved for a future where extensions need to export their own services; it returns an empty array today and requires a concrete consumer to justify filling it in.
- Exceptions inside `configure()` are caught and traced, never propagating into the Moodle DI boot — an operational detail that matters more than it looks, since a boot-time throw there could break the whole site.

## Out of scope

- The full outbound mechanics, the external-consumption code paths (recommended vs. fallback), and the anti-pattern list — see REF-MDL-006-01.
- The general Tier/registry model that classifies DI as a "non-trivial integration needing a complementary ADR" — see [MDL-005](./MDL-005-api-coverage-registry.md).

## Links

- [REF-MDL-006-01 — DI Bridge Mechanics & Consumption Patterns](../ref/REF-MDL-006-01-di-bridge-mechanics.md)
- [MDL-001 — Consolidate the Moodle Boundary Behind a Physical Whitelist](./MDL-001-boundary-consolidation-whitelist.md)
- [MDL-005 — API Coverage Registry / Tier Model](./MDL-005-api-coverage-registry.md)
- [CLAUDE.md](../../CLAUDE.md) — current implementation map (`Support/DiBridgeSupport`)
