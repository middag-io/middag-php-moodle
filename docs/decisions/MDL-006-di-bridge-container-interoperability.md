---
title: 'One-way, curated bridge to the Moodle DI container'
status: accepted
date: 2026-04-08
last-updated: 2026-11-04
lang: en
domains: [di, runtime]
deciders: [leadership]
decision: 'The framework container stays separate from core\di; listed services are exported one way via di_configuration.'
related: [MDL-001, MDL-005, MDL-017, middag-php-framework:FW-012]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# MDL-006: One-way, curated bridge to the Moodle DI container

## Context

Since Moodle 4.4, Moodle has its own PSR-11 container (`core\di`), filled through the `di_configuration` hook. The framework has its own compiled container. Other plugins on the same site may want to receive a consumer's services through Moodle's container. The question is how the two containers relate.

## Considered Options

1. No bridge: the containers stay isolated and other plugins build services by hand.
2. A two-way bridge, where adapter and framework code also resolve Moodle services from `core\di`.
3. Merge the containers, replacing one with the other.
4. Export every public service automatically.
5. Separate containers, with a one-way bridge from the framework to Moodle that exports only the services the consumer lists. ← chosen

## Decision

The framework container and `core\di` stay separate, and the framework container is built and compiled exactly as on any other host. The bridge works in one direction only: when Moodle fires `di_configuration`, the adapter adds the services that the consumer's composition root registered for export. The adapter ships no exports of its own, and nothing is exported automatically. In the other direction there is no bridge: when adapter code needs a Moodle service, it reaches it through a support wrapper, never through `core\di`.

## Consequences

- (+) Other plugins on Moodle 4.4+ can receive a consumer's public services through Moodle's own container, including autowiring.
- (+) The framework never depends on the internals of Moodle's container.
- (−) The exported set is a manual list that the consumer has to keep.
- (−) The bridge does nothing below Moodle 4.4.
- (−) An export that Moodle's builder rejects is traced and skipped, not raised, so a misconfigured export fails quietly.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Exports are added only after `di_configuration` fires, a failing export is isolated, and the bridge is off below 4.4 | test `tests/Support/DiBridgeSupportCoverageTest.php` | coded |
| Consumption patterns and anti-patterns | doc `docs-middag-dev:docs/framework/reference/adapters/moodle/di-bridge-mechanics.md` | coded |
| No resolution from `core\di` inside `src/` | static-analysis rule | planned |
