---
id: MDL-006
title: 'DI Bridge: Unidirectional, Curated Exposure to the Moodle Container'
status: accepted
date: 2026-04-08
lang: en
domains: [moodle, di]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-001, MDL-005]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/di-bridge-mechanics]
decision: 'The DI bridge exposes framework services to Moodle core\di outbound only, via a curated manual EXPORTS list (di_bridge_support) — there is no inbound bridge; framework code always resolves Moodle services through the boundary (support/adapter), never via core\di::get() directly.'
---

# MDL-006: DI Bridge — Unidirectional, Curated Exposure to the Moodle Container

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-207`, decided 2026-04-08, with `ref-207-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle 4.4+ introduced its own PHP-DI (PSR-11) container via `\core\di`, with autowiring and registration through the `di_configuration` hook. The framework already maintains its own container. Two containers now coexist on the same site, and without an explicit boundary rule, framework services could end up resolving through `\core\di::get()` directly — reintroducing exactly the kind of ad hoc platform coupling the boundary (MDL-001) exists to prevent.

## Considered Options

1. **No bridge** — keep the two containers fully isolated, so external plugins would have to instantiate MIDDAG services manually — rejected: blocks the DI-native consumption pattern (autowiring in native Moodle controllers) external plugins need.
2. **Bidirectional bridge** — let framework code resolve Moodle services directly via `\core\di::get()` — rejected: reintroduces the ad hoc platform coupling the boundary (MDL-001) exists to prevent; framework access to Moodle services stays behind the boundary (support/adapter) regardless of this ADR.
3. **Merge the two containers into one** (replace the framework's own container factory with Moodle's, or vice versa) — rejected: no change to the framework's own container factory or register/boot/compile lifecycle.
4. **Auto-discovery of exportable services** (any `@api` service exposed automatically) — rejected: the exposure criterion is manual and deliberate, only `@api` (Group A) services with demonstrated external-consumer utility.
5. **Curated, unidirectional, outbound-only bridge** (`di_bridge_support`) ← chosen.

## Decision

The two containers stay **separated**, with **selective, unidirectional** exposure — framework to Moodle DI only:

| Direction                         | Mechanism                                                                             | When to use                                           |
|-----------------------------------|-----------------------------------------------------------------------------------------|-------------------------------------------------------|
| Framework -> Moodle DI (outbound) | `di_bridge_support` registers services via the `di_configuration` hook                | Expose services for external consumption              |
| Moodle DI -> Framework (inbound)  | No container bridge exists — the boundary layer (support/adapter) encapsulates access | Framework needs a Moodle service (e.g. `\core\clock`) |

**Absolute rule**: framework services never resolve via `\core\di::get()` — always through the boundary (support/adapter). There is no change to the framework's own container factory or register/boot/compile lifecycle. Outbound, `di_bridge_support::configure(\core\hook\di_configuration $hook)` exposes a **curated** list of services (`EXPORTS`) — today, only the main facade (`middag::class`, via `middag::get_instance()`). The exposure criterion is manual and deliberate: only `@api` (Group A) services with demonstrated external-consumer utility, never auto-discovery. Registration happens through the static-generation pipeline (MDL-016), gated with `min_moodle: '4.4'` so the callback never runs on older Moodle. `di_bridge_support::is_available()` performs the explicit availability check (`version_support::supports('moodle_di_hook')` plus a `class_exists()` check).

## Consequences

- External plugins on Moodle >= 4.4 can resolve a MIDDAG service through their own native DI (`\core\di::get()`), including autowiring in their own controllers, without the framework ever depending on Moodle's container internals (current implementation: `Support/DiBridgeSupport`) — see `framework/reference/adapters/moodle/di-bridge-mechanics` (in `docs-middag-dev`) for the full consumption code paths (recommended vs. fallback) and the anti-pattern list.
- The curated, manual `EXPORTS` list keeps the exposed surface deliberately small — today a single entry — trading completeness for a surface the team can reason about fully.
- `get_extension_exports()` is reserved for a future where extensions need to export their own services; it returns an empty array today and requires a concrete consumer to justify filling it in.
- Exceptions inside `configure()` are caught and traced, never propagating into the Moodle DI boot — an operational detail that matters more than it looks, since a boot-time throw there could break the whole site.
- The general Tier/registry model that classifies DI as a "non-trivial integration needing a complementary ADR" is [MDL-005](./MDL-005-api-coverage-registry.md)'s concern, not repeated here.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Outbound-only bridge; consumption patterns (native `\core\di::get()`, autowiring, `< 4.4` fallback) and anti-patterns | doc `framework/reference/adapters/moodle/di-bridge-mechanics` | **coded** |
| `configure()` isolates a failing export per-id and never propagates exceptions into the Moodle DI boot | `tests/Support/DiBridgeSupportCoverageTest.php` (`testConfigureSwallowsHookErrors`, `testConfigureIsolatesAFailingExportPerId`) | **coded** |
| `is_available()` gates the bridge to Moodle >= 4.4 (`VersionSupport::supports('moodle_di_hook')` + symbol check) | `tests/Support/DiBridgeSupportCoverageTest.php` (`testIsAvailableReturnsTrueOnSupportedMoodle`, `testIsAvailableReturnsFalseOnUnsupportedMoodle`) | **coded** |
| Framework services never resolve via `\core\di::get()` directly — always through the boundary (support/adapter) | no automated architecture/static-analysis check found | **planned** |
| `EXPORTS` stays manual/curated — no auto-discovery of `@api` services | no automated check | **planned** |
