---
title: 'Settings as code, resolved at runtime'
status: accepted
date: 2026-04-05
last-updated: 2026-11-04
lang: en
domains: [settings]
deciders: [leadership]
decision: 'Settings are a typed DSL bridged to Moodle by one resolver; key prefixes come from an injectable neutral policy.'
related: [MDL-001, MDL-012, MDL-016, MDL-021, middag-php-framework:FW-015]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# MDL-011: Settings as code, resolved at runtime

## Context

Moodle admin settings are built from `admin_setting_*` classes, each with its own constructor conventions, and stored under config keys that every plugin on the site shares a namespace for. Consumers need to declare settings without wiring Moodle classes by hand, and two consumers on one site must not collide or inherit each other's brand. The question is how settings are declared, how they reach Moodle and who decides their keys.

## Considered Options

1. Each consumer builds `admin_setting_*` objects directly.
2. Generate the settings file at build time, like the other plugin static files.
3. A typed DSL with a fixed, product-branded key prefix.
4. A typed DSL that is the only way to declare settings, turned into Moodle settings at runtime by one resolver, with key prefixes coming from an injectable naming policy whose default is neutral. ← chosen

## Decision

Consumers declare admin settings with the adapter's typed settings classes and pages, never by building `admin_setting_*` objects. One resolver turns those declarations into Moodle settings pages when Moodle loads its admin tree; settings are not generated at build time. Runtime reads and writes go through the settings support with typed keys.

Config key names come from a naming policy that the consumer injects. The policy's default prefix belongs to no product: it marks keys of a consumer that configured nothing. A real product injects its own prefix at its composition root, so a third party never inherits another company's brand in its keys.

## Consequences

- (+) Every setting reaches Moodle through one typed path, and `admin_setting_*` wiring lives in one place.
- (+) No product brand leaks into the keys of a consumer that configures nothing.
- (−) A consumer that forgets to inject its prefix silently reads and writes keys under the neutral default.
- (−) The static key helper `SettingsResolver::resolveConfigKey()`, and the supports when called without a policy, always use the default policy and ignore the one the consumer injected (`src/Settings/SettingsResolver.php:65`).
- (−) Settings are resolved at runtime while the other plugin static files are generated (MDL-016), so declarations follow two mechanisms.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Neutral default prefix, and an injected policy overrides it | test `tests/Settings/SettingsNamingPolicyTest.php` | coded |
| The resolver turns typed pages into Moodle settings pages under its policy | test `tests/Settings/SettingsResolverCoverageTest.php` | coded |
| Settings types, key shape and the injection points | doc `docs-middag-dev:docs/framework/reference/adapters/moodle/settings-and-config-keys.md` | coded |
| No `admin_setting_*` object built outside `Settings/` | static-analysis rule | planned |
