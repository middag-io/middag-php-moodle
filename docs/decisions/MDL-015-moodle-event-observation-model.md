---
id: MDL-015
title: 'Dual Inbound/Outbound Model for Moodle Event Observation'
status: accepted
date: 2026-04-04
domains: [moodle, events]
related: [MDL-003, MDL-016]
supersedes: []
superseded_by: null
lang: en
---

# MDL-015: Dual Inbound/Outbound Model for Moodle Event Observation

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-304`, decided 2026-04-04, with `REF-304-01` as companion). Relocated in the source consolidation from a "core" grouping into this Moodle-boundary document, because it describes how the framework observes/adapts to native Moodle events, not generic domain code. This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

The framework needs to react to Moodle's own events (inbound) and also emit its own domain activity into Moodle's logstore (outbound), without letting either direction turn into ad hoc, per-extension wiring with no shared convention for how an event becomes an internal signal or vice versa.

## Decision

**Inbound** (Moodle event to internal signal) supports three coexisting paths, never duplicated for the same event: a manual observer (`middag_observer`) with explicit signal publication, used when data enrichment is needed; `moodle_event_bridge::register()`/`register_declarative()` called from `boot()`; and the preferred, boilerplate-free path — a `#[moodle_event(...)]` attribute on the signal class, auto-discovered by `moodle_signal_loader` (requires the `_signal.php` file suffix). Registering the same event class through more than one path is a configuration error; the bridge's behavior is **last-write-wins**, with no silent fallback (unlike the item-type-system collision rule, which treats identifier collisions as fatal). A dual observer model exists: `catch_all` (wildcard `*`, gated by `moodle_events_catch_all` config, recommended OFF in production) versus `observe_registered` (explicit events via `get_moodle_events()`). **Outbound** (framework to Moodle logstore): extensions declare `get_event_definitions()` (typed `event_definition`); the static-generation pipeline ([MDL-016](./MDL-016-moodle-statics-generation.md)) generates classes under `classes/event/` extending `\core\event\base`, named `{extension}_{entity}_{action}`. Outbound emission may optionally also fire an internal signal. Signal naming is hierarchical (`aggregate_signal_interface`): `middag/{aggregate}/{action}` (general) plus `middag/{aggregate}/{action}/{type}` (specific). External plugins expose signal-class directories via one hook: `{plugin}_extend_local_middag_moodle_signal_loader(array $dirs): array`.

## Consequences

- Three inbound paths cover the full spectrum from "no boilerplate" (attribute-based) to "needs enrichment logic" (manual observer), without forcing every event handler into the same shape.
- Last-write-wins on duplicate registration is a real footgun if a contributor is unaware of it — mitigated only by documentation, not by a hard failure, unlike the item-type system's stricter collision rule.
- `catch_all` being available but recommended OFF in production means the wildcard observer exists as an escape hatch, not the default operating mode — its performance cost (invoked for every Moodle event) is explicit.

## Out of scope

- Naming detail, the full anti-pattern list, and the external-plugin signal-directory hook's exact signature — see REF-MDL-015-01.
- The static-generation pipeline that produces the outbound event classes — see [MDL-016](./MDL-016-moodle-statics-generation.md).

## Links

- [REF-MDL-015-01 — Inbound Paths, Naming & Anti-Patterns](../ref/REF-MDL-015-01-event-observation-detail.md)
- [MDL-003 — Support Layer Pattern](./MDL-003-support-layer-pattern.md)
- [MDL-016 — Moodle Statics Generation](./MDL-016-moodle-statics-generation.md)
