---
id: MDL-015
title: 'Dual Inbound/Outbound Model for Moodle Event Observation'
status: accepted
date: 2026-04-04
lang: en
domains: [moodle, events]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-003, MDL-016]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/event-observation-detail]
decision: 'Moodle events are observed inbound via three coexisting, last-write-wins registration paths (attribute-based preferred, bridge registration, manual observer for enrichment) and emitted outbound via generated `\core\event\base` subclasses per extension, both sides unified under a hierarchical `middag/{aggregate}/{action}` internal signal namespace.'
---

# MDL-015: Dual Inbound/Outbound Model for Moodle Event Observation

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-304`, decided 2026-04-04, with `REF-304-01` as companion). Relocated in the source consolidation from a "core" grouping into this Moodle-boundary document, because it describes how the framework observes/adapts to native Moodle events, not generic domain code. This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

The framework needs to react to Moodle's own events (inbound) and also emit its own domain activity into Moodle's logstore (outbound), without letting either direction turn into ad hoc, per-extension wiring with no shared convention for how an event becomes an internal signal or vice versa.

## Considered Options

1. **Force every inbound Moodle event through a single registration mechanism** (e.g. manual observers only, or attribute-discovery only). Rejected — no single shape fits both "no boilerplate" pass-through mappings and enrichment-heavy mappings equally well; the three coexisting paths exist specifically to cover that spectrum.
2. **Treat duplicate registration of the same event across paths as a fatal configuration error**, mirroring the item-type system's identifier-collision rule. Rejected — the bridge instead resolves duplicates by last-write-wins, silently; the stricter fatal-collision behavior stays specific to the item-type system, not adopted here.
3. **Recommend `catch_all` (wildcard observer) as the default production mode.** Rejected — `catch_all` is gated behind the `moodle_events_catch_all` config and recommended OFF in production, given the cost of being invoked for every Moodle event site-wide; `observe_registered` (explicit event list) is the default instead.
4. **Three coexisting inbound paths (attribute-based preferred, bridge registration, manual observer for enrichment), last-write-wins on duplicate registration, and `catch_all` as an opt-in escape hatch** ← chosen.

## Decision

**Inbound** (Moodle event to internal signal) supports three coexisting paths, never duplicated for the same event: a manual observer (`middag_observer`) with explicit signal publication, used when data enrichment is needed; `moodle_event_bridge::register()`/`register_declarative()` called from `boot()`; and the preferred, boilerplate-free path — a `#[moodle_event(...)]` attribute on the signal class, auto-discovered by `moodle_signal_loader` (requires the `_signal.php` file suffix). Registering the same event class through more than one path is a configuration error; the bridge's behavior is **last-write-wins**, with no silent fallback (unlike the item-type-system collision rule, which treats identifier collisions as fatal). A dual observer model exists: `catch_all` (wildcard `*`, gated by `moodle_events_catch_all` config, recommended OFF in production) versus `observe_registered` (explicit events via `get_moodle_events()`). **Outbound** (framework to Moodle logstore): extensions declare `get_event_definitions()` (typed `event_definition`); the static-generation pipeline ([MDL-016](./MDL-016-moodle-statics-generation.md)) generates classes under `classes/event/` extending `\core\event\base`, named `{extension}_{entity}_{action}`. Outbound emission may optionally also fire an internal signal. Signal naming is hierarchical (`aggregate_signal_interface`): `middag/{aggregate}/{action}` (general) plus `middag/{aggregate}/{action}/{type}` (specific). External plugins expose signal-class directories via one hook: `{plugin}_extend_local_middag_moodle_signal_loader(array $dirs): array`.

## Consequences

- Three inbound paths cover the full spectrum from "no boilerplate" (attribute-based) to "needs enrichment logic" (manual observer), without forcing every event handler into the same shape.
- Last-write-wins on duplicate registration is a real footgun if a contributor is unaware of it — mitigated only by documentation, not by a hard failure, unlike the item-type system's stricter collision rule.
- `catch_all` being available but recommended OFF in production means the wildcard observer exists as an escape hatch, not the default operating mode — its performance cost (invoked for every Moodle event) is explicit.
- The outbound half of this model depends on the static-generation pipeline that produces the generated `\core\event\base` classes — see [MDL-016](./MDL-016-moodle-statics-generation.md), not restated here.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Registering the same event class through more than one inbound path (attribute, bridge, manual observer) is a configuration error | Code review; no automated check found | planned |
| `catch_all` (wildcard observer) must stay gated behind `moodle_events_catch_all` config, OFF by default in production | Code review; no automated check found | planned |
| Outbound event classes are named `{extension}_{entity}_{action}`, generated under `classes/event/` extending `\core\event\base` | Generated by the static-generation pipeline ([MDL-016](./MDL-016-moodle-statics-generation.md)) | planned |
| Inbound path preference order, signal naming hierarchy (`middag/{aggregate}/{action}` and `middag/{aggregate}/{action}/{type}`), and the anti-pattern list | [Event Observation: Inbound Paths, Naming & Anti-Patterns](https://docs.middag.dev/framework/reference/adapters/moodle/event-observation-detail) | coded |
