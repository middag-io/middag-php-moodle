---
title: "Static, stateless wrappers for Moodle's procedural APIs"
status: accepted
date: 2026-04-04
last-updated: 2026-11-04
lang: en
domains: [boundary, api]
deciders: [leadership]
decision: 'One static, stateless *Support wrapper per Moodle subsystem plus an aggregator; facades belong to consumers.'
related: [MDL-001, MDL-002, middag-php-framework:FW-016]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# MDL-003: Static, stateless wrappers for Moodle's procedural APIs

## Context

Most of Moodle's API is procedural: global functions, globals such as `$DB` and `$CFG`, and core classes that return `false` or untyped records. Both object-oriented adapter code and procedural Moodle entry points that have no container need to call it. The question is what shape the code that wraps those calls takes.

## Considered Options

1. Call Moodle functions and globals directly wherever they are needed.
2. Only injectable instance services, with no static wrappers.
3. Static wrappers plus product facades shipped inside the adapter.
4. One static wrapper class per Moodle subsystem: no state, typed returns, no business logic, reachable through a single static aggregator. The facades a product generates for its own services live in the consumer plugin. ← chosen

## Decision

Each Moodle subsystem the adapter covers has one `*Support` class. It translates Moodle's procedural interface into typed calls and makes no decisions of its own. Support classes hold no state. The only exceptions are the categories FW-016 allows: a registration seam the composition root writes, and a cache of immutable host data such as the Moodle version. The `Support\Moodle` aggregator is the single static entry point to all of them. The adapters that implement framework ports delegate to these wrappers. The facade mechanism is generic, but the facades themselves are generated for and owned by the consumer plugin, never added to this package.

## Consequences

- (+) Each Moodle API is wrapped in exactly one place, so a Moodle change is absorbed once.
- (+) Moodle's `false` and untyped returns stop at the wrapper; callers get nullable or typed values.
- (+) Procedural Moodle entry points can call the wrappers without a container.
- (−) Static calls are harder to replace in a consumer's tests than injected services. The package tests run against Moodle function stubs instead.
- (−) The same subsystem can be reached two ways, through a port by injection or through a static wrapper.
- (−) Whether a new wrapper is justified is a review judgment, not a mechanical rule.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Wrapper inventory, aggregator methods and anti-patterns | doc `docs-middag-dev:docs/framework/reference/adapters/moodle/support-class-inventory.md` | coded |
| Every wrapper is covered without a Moodle runtime | tests `tests/Support/*` | coded |
| The aggregator returns a fresh wrapper per call | test `tests/Support/MoodleCoverageTest.php` | coded |
| No business logic and no state outside the FW-016 categories in `Support/` | review | planned |
