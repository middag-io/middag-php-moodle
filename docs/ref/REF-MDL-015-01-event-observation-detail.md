---
ref: REF-MDL-015-01
adr: MDL-015
title: 'Inbound Paths, Naming & Anti-Patterns'
lang: en
---

# REF-MDL-015-01: Inbound Paths, Naming & Anti-Patterns

> Detail supporting [MDL-015](../decisions/MDL-015-moodle-event-observation-model.md). Reconstructed from the `moodle-local_middag` legacy vault (`ADR-304`, `REF-304-01`).

## Inbound paths, in order of preference

1. **Attribute-based (preferred)** — `#[moodle_event(...)]` on the signal class, auto-discovered by `moodle_signal_loader`. Requires the file to be suffixed `_signal.php`; without the suffix, the attribute is present but discovery does not happen.
2. **Bridge registration** — `moodle_event_bridge::register()`/`register_declarative()`, called from `boot()`. A middle-ground path when the attribute convention does not fit.
3. **Manual observer** — `middag_observer` with explicit signal publication. Used specifically when the Moodle event needs data enrichment before becoming an internal signal — not for simple pass-through mappings.

## Duplicate-registration behavior

Registering the same Moodle event class through more than one of the three paths is a configuration error. The bridge's actual behavior on duplication is **last-write-wins**, silently — there is no fallback and no hard failure. This differs from the item-type system's rule (a separate mechanism, out of scope here), where an identifier collision is fatal rather than resolved by precedence.

## Signal naming

`aggregate_signal_interface` hierarchy: `middag/{aggregate}/{action}` (general) and `middag/{aggregate}/{action}/{type}` (specific variant).

## Outbound naming

`{extension}_{entity}_{action}`, namespace `\local_middag\event\` (or `\{plugin}\event\` for external plugins).

## External plugin extension point

`{plugin}_extend_local_middag_moodle_signal_loader(array $dirs): array` — the single hook external plugins implement to expose their own signal-class directories to the loader.

## Anti-patterns

- Registering the same event through two different paths.
- A signal class carrying `#[moodle_event]` without the required `_signal.php` suffix — it will not be discovered, silently.
- Using a manual observer for a direct, no-enrichment mapping — unnecessary boilerplate when the attribute path would do.
- Leaving `catch_all` (wildcard) ON in production — a real performance cost, since it is invoked for every Moodle event site-wide.
