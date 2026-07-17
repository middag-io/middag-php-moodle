---
id: MDL-012
title: 'Config Schema: a Static, Service-Free Source of Truth for Setting Keys'
status: accepted
date: 2026-04-09
domains: [moodle, settings]
related: [MDL-011, MDL-003]
supersedes: []
superseded_by: null
lang: en
---

# MDL-012: Config Schema — a Static, Service-Free Source of Truth for Setting Keys

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-312`, decided 2026-04-09, with `ref-312-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

[MDL-011](./MDL-011-admin-settings-declaration-lifecycle.md) established how settings get declared and registered, but left implicit what the actual source of truth is for a given key's type, default, and options — and that question needs an answer that works at **any** point in the lifecycle, including phases where services are not yet available.

## Decision

Each extension with settings gets an enum `{slug}_config` (string-backed) implementing `config_schema_interface` — the source of truth for keys, types, defaults, and static options, queryable at any lifecycle phase without loading services:

```php
interface config_schema_interface {
    public function type(): setting_type;
    public function default(): mixed;
    public function options(): ?array;   // null = no options, or dynamic (lazy)
    public function is_lazy(): bool;     // true = needs service resolution at runtime
}
```

The `setting_type` enum covers every native `admin_setting_config*` type (22 variants, 19 with storage plus 3 display-only: `heading`, `description`, `link`); `stores_value(): bool` distinguishes the two groups. Settings marked `is_lazy() === true` return `options() === null`; the real resolution happens only at admin-UI render time, inside `get_settings_pages()`, where services are available. **Critical rule**: lazy options are never resolved during `register()` or `compile()` (boot phase) — only at render time (an HTTP request context). The `{slug}_config` enum itself never resolves dynamic options — it only marks `is_lazy`; resolution belongs to the DSL from MDL-011, not the enum. `settings_support` (runtime API: `get(BackedEnum $key)`, `set(BackedEnum $key, mixed $value)`, `schema(string $slug)`) accepts only a `BackedEnum` implementing `config_schema_interface`; `schema()` returns static metadata without loading services, safe at any phase.

## Consequences

- A setting's type/default/options are queryable from a pure enum, with zero service-loading cost — useful anywhere in the lifecycle, including very early boot.
- The lazy-options rule (resolve only at render time, never at boot) prevents a whole class of "service not ready yet" bugs, at the cost of requiring every lazy setting's author to remember the distinction.
- The enum-vs-DSL split (enum is pure metadata, DSL does resolution) keeps the schema static and cheap while still allowing genuinely dynamic option lists where needed.

## Out of scope

- The full per-extension config-key inventory, the current DSL-implementation coverage, and the anti-pattern list — see REF-MDL-012-01.
- The settings declaration/registration lifecycle itself — see [MDL-011](./MDL-011-admin-settings-declaration-lifecycle.md).

## Links

- [REF-MDL-012-01 — Config Key Inventory & DSL Coverage](../ref/REF-MDL-012-01-config-schema-inventory.md)
- [MDL-011 — Admin Settings Declaration Lifecycle](./MDL-011-admin-settings-declaration-lifecycle.md)
- [MDL-003 — Support Layer Pattern](./MDL-003-support-layer-pattern.md)
