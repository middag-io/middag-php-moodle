---
title: 'Normal Composer dependencies, without namespace scoping'
status: accepted
date: 2026-07-27
last-updated: 2026-11-04
lang: en
domains: [dependencies]
deciders: [leadership]
decision: 'Normal namespaces, no scoping; overlap with Moodle-bundled libraries is managed by major checks and a small footprint.'
related: [MDL-001, MDL-005]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# MDL-020: Normal Composer dependencies, without namespace scoping

## Context

Moodle core bundles some third-party libraries and registers their namespaces globally on every request, outside Composer. When this adapter requires a package in one of those namespaces, PHP loads whichever copy its autoloaders reach first, and that order depends on how the consumer bootstraps. A major-version gap between the two copies can break at runtime. The question is how the adapter declares such dependencies.

## Considered Options

1. Rewrite the namespaces of shared dependencies at build time (scoping).
2. Declare nothing that Moodle bundles and rely on Moodle's copy.
3. Leave the overlap undocumented, as a generic Moodle plugin problem.
4. Declare dependencies normally, check each overlapping package's major version against the copy Moodle bundles before a bump or a raise of the supported Moodle range, keep the dependency footprint small and remove overlapping packages that are not used. ← chosen

## Decision

The adapter declares its Composer dependencies with their normal namespaces, with no build-time scoping and no reliance on Moodle's bundled copies. Packages whose namespaces Moodle also registers are a known risk, managed by process: before such a package's major version is bumped, or the supported Moodle range is raised, its major is checked against what those Moodle versions bundle. The footprint stays small, with a contracts package wherever interfaces are enough, and an overlapping package the adapter does not use is removed rather than kept. Which copy wins at runtime is a property of the consumer's bootstrap order, which this package cannot fix.

## Consequences

- (+) `composer.json` states what the adapter really runs against, and its versions move independently of Moodle's.
- (+) No build step is added to an OSS library.
- (−) The winning copy depends on the consumer's bootstrap order, so a major gap can still surface in one hosting layout and not another.
- (−) The checks are manual.
- (−) `firebase/php-jwt` is required (`composer.json`, `^7.0`) but nothing in `src/` uses it, while Moodle 5.0 bundles a 6.x copy in the same namespace.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Overlapping namespaces and the known version gaps | doc `docs-middag-dev:docs/framework/reference/adapters/moodle/shared-namespace-dependencies.md` | planned |
| Major check of overlapping packages before a bump | CI diff against Moodle's bundled namespace map | planned |
| No unused overlapping dependency | dependency-usage check | planned |
