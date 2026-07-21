---
id: MDL-012
title: 'Config Schema: a Static, Service-Free Source of Truth for Setting Keys'
status: accepted
date: 2026-04-09
lang: en
domains: [moodle, settings]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-011, MDL-003]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/config-schema-inventory]
decision: 'Each extension with settings gets a static, service-free `{slug}_config` enum (implementing `config_schema_interface`) as the single source of truth for a setting key''s type, default, and options — with lazy (service-dependent) options marked via `is_lazy()` but resolved only at admin-UI render time, never during boot/register/compile.'
---

# MDL-012: Config Schema — a Static, Service-Free Source of Truth for Setting Keys

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-312`, decided 2026-04-09, with `ref-312-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

[MDL-011](./MDL-011-admin-settings-declaration-lifecycle.md) established how settings get declared and registered, but left implicit what the actual source of truth is for a given key's type, default, and options — and that question needs an answer that works at **any** point in the lifecycle, including phases where services are not yet available.

## Considered Options

1. Resolve a setting's type, default, and options via runtime service calls, with no static schema of its own. Rejected — this only works once services are available, leaving early lifecycle phases (and any code that needs metadata before service resolution) without an answer, which is exactly the gap left open by MDL-011.
2. Let the `{slug}_config` enum resolve dynamic (lazy) options itself, inline. Rejected — this would mix static metadata with resolution logic inside the enum; resolution belongs to the DSL from MDL-011, keeping the enum pure and queryable at zero service-loading cost.
3. Resolve lazy options during `register()`/`compile()` (boot phase), the same as static options. Rejected — services are not guaranteed available at boot; resolving eagerly there risks a whole class of "service not ready yet" bugs. Lazy options are deferred to render time only, inside `get_settings_pages()`.

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
- `settings_support` — the runtime API this schema backs — is itself one of the `*_support` classes governed by the pattern in [MDL-003](./MDL-003-support-layer-pattern.md): static, stateless, one class per concern; its `schema()` method is what lets this static metadata be queried without loading services.
- The full per-extension config-key inventory, the current DSL type-coverage state, and the config-schema anti-pattern list live in the extracted reference doc, not in this ADR (see Enforcement).
- The settings declaration/registration lifecycle itself (inline vs. dedicated-file mode, naming conventions, the `settings_resolver` bridge) is a separate concern, covered by [MDL-011](./MDL-011-admin-settings-declaration-lifecycle.md) — not restated here.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Each extension with settings has exactly one `{slug}_config` enum implementing `config_schema_interface`, queryable at any lifecycle phase without loading services | no coded rule yet — judgment call at review time | **planned** |
| Lazy options (`is_lazy() === true`) are never resolved during `register()`/`compile()` (boot phase) — only at render time, inside `get_settings_pages()` | no automated check found; code review only | **planned** |
| The `{slug}_config` enum stays pure metadata — it never resolves dynamic options itself; resolution belongs to the DSL ([MDL-011](./MDL-011-admin-settings-declaration-lifecycle.md)) | no coded rule yet | **planned** |
| Full per-extension config-key inventory, current DSL type-coverage state, and anti-pattern catalog | doc `framework/reference/adapters/moodle/config-schema-inventory` | **coded** |
