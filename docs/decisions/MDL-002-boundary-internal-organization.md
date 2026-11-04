---
title: 'The Moodle host domain is adapter-local surface'
status: accepted
date: 2026-03-29
last-updated: 2026-11-04
lang: en
domains: [boundary, api]
deciders: [leadership]
decision: 'Typed Moodle host types live in Domain/<Subsystem> with local contracts and @api, never promoted to framework ports.'
related: [MDL-001, MDL-003, MDL-004, MDL-005, middag-php-framework:FW-015]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# MDL-002: The Moodle host domain is adapter-local surface

## Context

Consumers work with Moodle's own objects: users, courses, enrolments, grades, groups, files, tasks and more. Moodle hands them out as untyped records. They need a typed and stable form, but they are Moodle concepts, not concepts every host shares. The question is where these types live and what status they have.

## Considered Options

1. Promote each Moodle subsystem to a host-neutral framework port.
2. Return Moodle's untyped records and let each consumer type them.
3. Keep entities, DTOs and services in flat folders by technical type, with no grouping by Moodle subsystem.
4. A typed Moodle host domain inside the adapter, grouped by Moodle subsystem, where each subsystem has its own local contracts and its entities and DTOs are public API by default, with explicit internal opt-outs. ← chosen

## Decision

The Moodle host domain is surface of this adapter. Each Moodle subsystem the adapter covers is a folder under `Domain/` with its entities, DTOs, enums and services. Services implement interfaces in that subsystem's local `Contract/` folder. Those interfaces are not framework ports: the framework never sees them, and another host has no equivalent. Entities and DTOs are public API (`@api`) by default, because consumers use them directly. A class that only serves the adapter's own infrastructure opts out with `@internal`.

## Consequences

- (+) Consumers get typed Moodle objects with a stable contract instead of records whose shape is unchecked.
- (+) The framework port set stays host-neutral; Moodle-only concepts do not leak into it.
- (−) Code that depends on these types is tied to Moodle. A product that needs the same behaviour on another host must put its own port in front of them.
- (−) Public by default means every entity and DTO falls under the package's API stability policy (`API-STABILITY.md`), so a rename needs a deprecation.
- (−) The package has two axes: top-level folders by technical role, and subsystem folders inside `Domain/`.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Layout of host-domain folders and local `Contract/` folders | doc `docs-middag-dev:docs/framework/reference/module-layout-convention.md` | coded |
| Covered subsystems and the `@api`/`@internal` classification of their types | doc `docs-middag-dev:docs/framework/reference/adapters/moodle/api-coverage-inventory.md` | coded |
| Every type carries an `@api` or `@internal` tag | governance gate run from outside the package | planned |
| Local `Contract/` interfaces are never framework ports | review | planned |
