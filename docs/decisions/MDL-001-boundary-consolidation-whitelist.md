---
title: 'Moodle coupling is confined to this OSS adapter'
status: accepted
date: 2026-03-29
last-updated: 2026-11-04
lang: en
domains: [adapters, boundary, runtime]
deciders: [leadership]
decision: 'Only this OSS adapter calls Moodle APIs; it implements framework ports and ships no identity or composition root.'
related: [MDL-002, MDL-003, MDL-006, MDL-011, MDL-016, MDL-017, MDL-020, middag-php-framework:FW-011, middag-php-framework:FW-015, middag-php-framework:FW-016, middag-php-wordpress:WP-002, docs-middag-ops:ADR-020]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# MDL-001: Moodle coupling is confined to this OSS adapter

## Context

Products that run inside Moodle need its database, capabilities, files, tasks, events and mandatory plugin callbacks. The framework stays host-free, and a host enters only through an adapter that implements framework ports. The question is where Moodle coupling lives, what that code may depend on, and how Moodle's procedural entry points reach object-oriented code.

## Considered Options

1. A Moodle subtree inside the framework, with a whitelist of the host calls it may make.
2. Each product plugin calls Moodle APIs directly from its own domain and service code.
3. A Moodle adapter that also carries a product's composition root, identity and proprietary concerns.
4. One OSS adapter package that is the only code calling Moodle APIs. It implements the framework ports, imports no proprietary code, receives the consumer plugin's identity at runtime and ships no composition root. Moodle's mandatory plugin APIs are thin adapters inside it, and its entry point is a static Kernel. ← chosen

## Decision

Moodle coupling lives in this package and nowhere else. The adapter implements the framework ports on top of Moodle's runtime APIs, and nothing in it names a proprietary MIDDAG namespace or product data. It is component-agnostic: the consumer plugin configures its frankenstyle component and supplies the container builder at boot, so the adapter has no product identity and no composition root of its own. Concerns that need governance (licensing, entitlement, product data) live in downstream proprietary packages that extend this adapter, never the other way round.

Moodle's mandatory plugin APIs (privacy, backup and restore, file serving, navigation, `lib.php` callbacks) are thin adapters here: they translate Moodle's contract and delegate, and they carry no business logic. The bridge between the SPA page protocol and the Moodle page and theme is a host adapter here as well.

The adapter's entry point is a static Kernel. Moodle reaches a plugin through `lib.php` callbacks, hook callbacks, scheduled and ad hoc tasks and CLI scripts, none of which receive a container, so they need a static way in. This is a bounded static in the sense of FW-016. It is the deliberate opposite of the WordPress adapter (WP-002), where many plugins share one PHP process: there the singleton lives in each consumer's subclass and identity never comes from a global slot.

## Consequences

- (+) Moodle coupling can be audited in one package, and consumer domain code is testable without Moodle.
- (+) Any third party can build a Moodle plugin on the adapter without MIDDAG products.
- (+) Moodle's mandatory plugin APIs are wrapped once instead of once per product.
- (−) The Kernel and the configured component are single process-wide slots. Two consumer plugins built on this adapter in the same request share them, and the last configuration wins.
- (−) The thin-adapter rule for `lib.php` and the other Moodle entry points applies to files in the consumer plugin, which this package cannot check.
- (−) The adapter's exception classes extend SPL exceptions instead of the framework's exception family (`src/Exception/*.php`).

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| No proprietary MIDDAG namespace and no product table literal in `src/` | test `tests/AdapterPluginIsolationTest.php` | coded |
| Every class loads without a Moodle runtime, with documented exclusions | test `tests/ClassLoadabilityTest.php` | coded |
| Identity is injected at runtime and no composition root is shipped | tests `tests/Config/ComponentContextTest.php` (`testNameThrowsWhenUnconfigured`), `tests/Runtime/ContainerFactoryTest.php` (`getInstanceThrowsWhenNoBuilderIsRegistered`) | coded |
| Framework port to Moodle implementation mapping | doc `docs-middag-dev:docs/framework/reference/bridge-contracts.md` | coded |
| Moodle API whitelist and plugin-API adapters | doc `docs-middag-dev:docs/framework/reference/adapters/moodle/boundary-whitelist.md` | coded |
| SPA page and theme host bridge | doc `docs-middag-dev:docs/framework/reference/adapters/moodle/frontend-build-detail.md` | coded |
| Consumer code calls Moodle only through this adapter | static-analysis rule in consumers | planned |
