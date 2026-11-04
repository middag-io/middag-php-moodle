---
title: 'Multi-version support through runtime feature gates'
status: accepted
date: 2026-04-07
last-updated: 2026-11-04
lang: en
domains: [versioning, api]
deciders: [leadership]
decision: 'One codebase serves every supported Moodle version via version-range gates and symbol detection; no per-version branch.'
related: [MDL-001, MDL-002, MDL-006, MDL-016, MDL-017]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# MDL-005: Multi-version support through runtime feature gates

## Context

Consumer plugins install on several Moodle versions at once, and Moodle adds APIs between versions: a dependency-injection hook, a native router, new hook and check APIs. A class that names a missing core symbol cannot even load on an older host. The question is how one adapter serves all supported versions.

## Considered Options

1. A branch or release line per Moodle version.
2. Support only the newest Moodle version.
3. A hand-kept registry that classifies, API by API, how deeply each one is covered, as the means of deciding version support.
4. One codebase for every supported version. Features are gated at runtime by a Moodle version range or by detecting the core symbol. Code that touches a newer API names its symbols by string so the class still loads on older hosts, and typed definitions carry their own version range. ← chosen

## Decision

The adapter has one codebase and one release line for every Moodle version it supports. A feature that depends on a newer Moodle API is gated at runtime, either by a version range or by checking that the core symbol exists, and does nothing on a host that lacks it. Code that refers to a core class that may be missing does so by string, never by an import, so every class stays loadable. Definitions of generated plugin files carry a minimum and maximum Moodle version and report whether they apply to a given host version. Which APIs are covered, and from which version, is reference data, not a decision.

## Consequences

- (+) Consumers depend on one package version whatever Moodle they run.
- (+) New Moodle APIs are adopted without dropping older hosts.
- (−) Code paths for old versions stay in the codebase until the supported minimum is raised.
- (−) Static analysis has to run against each supported Moodle stub series, not one.
- (−) CI checks against Moodle stubs, not against a real Moodle install of each version.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Static analysis against every supported Moodle series | CI job `PHPStan (moodle-stubs …)` in `.github/workflows/ci.yml` (`~4.5.0` to `~5.2.0`) | coded |
| Version-range gates and symbol detection | tests `tests/Support/VersionSupportCoverageTest.php`, `tests/Support/VersionSupportSymbolTest.php` | coded |
| Every class loads on a host without the newer symbols | test `tests/ClassLoadabilityTest.php` | coded |
| Covered APIs and their version guards | doc `docs-middag-dev:docs/framework/reference/adapters/moodle/api-coverage-inventory.md` | coded |
