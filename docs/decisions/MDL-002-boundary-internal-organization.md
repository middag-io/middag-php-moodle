---
id: MDL-002
title: 'Organize the Boundary by Technical Type, Not by Moodle Subsystem'
status: accepted
date: 2026-03-29
domains: [moodle, boundary]
related: [MDL-001, MDL-003]
supersedes: []
superseded_by: null
lang: en
---

# MDL-002: Organize the Boundary by Technical Type, Not by Moodle Subsystem

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-202`, decided 2026-03-29). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Once the Moodle boundary was physically consolidated ([MDL-001](./MDL-001-boundary-consolidation-whitelist.md)), the boundary directory itself needed an internal shape. Mirroring Moodle's own subsystem taxonomy (courses, users, files, …) inside the boundary would let the folder tree reflect a domain that is not MIDDAG's own — Moodle's internal categorization is not something this project owns or controls, and it changes on Moodle's release cadence, not this project's.

## Decision

The boundary is organized **by technical type**, not by Moodle micro-domain: `adapter/`, `contract/`, `service/`, `definition/`, `dto/`, `entity/`, `enum/`, `settings/`, `form/`, `support/` (see table below). A new subdirectory is admitted only when it represents a **distinct native Moodle mechanism** that does not fit an existing one — file count is never the admission criterion.

| Subdirectory  | Purpose                                                                                                                                                       |
|---------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `adapter/`    | OO implementations of boundary contracts and DI bridges (`authentication`, `capability`, `logger`, `authorizer`, `transaction_manager`, `translator`, `view`) |
| `contract/`   | Boundary contracts — extension providers (`activity_feed_renderer`, `privacy_provider`, etc.) plus adapter/service contracts                                  |
| `service/`    | Moodle-specific services orchestrating 2+ supports (`auth_service`, `message_service`, `user_service`, `adhoc_service`, `scheduled_service`)                  |
| `definition/` | Typed definitions for Moodle static generation (`cache`, `capability`, `service`, `message`, `hook` — MDL-016)                                                |
| `dto/`        | Boundary transport objects — Group A (public API) by default                                                                                                  |
| `entity/`     | Typed representations of Moodle objects (`stdClass` → typed) — Group A by default                                                                             |
| `enum/`       | Typed enums for Moodle constants (contexts, risks, modes, permissions)                                                                                        |
| `settings/`   | Typed classes for admin settings (`page`, `text`, `password`, `select`, etc.)                                                                                 |
| `form/`       | Adapters for Moodle Forms (MoodleQuickForm) — a distinct mechanism                                                                                            |
| `support/`    | Static wrappers — one `*_support` class per subsystem (MDL-003)                                                                                               |

## Consequences

- The folder tree never has to be reshuffled when Moodle reorganizes its own internal subsystems — it tracks technical role (adapter vs support vs entity), which is stable regardless of Moodle's own taxonomy.
- New contributors orient by "what kind of thing is this" rather than "which Moodle subsystem does this touch," which matches how the whitelist enforcement (MDL-001) already reasons about the boundary.
- Admission of a new subdirectory requires a judgment call ("is this a genuinely distinct mechanism?") rather than a mechanical rule — accepted as the right tradeoff over a rigid, ever-growing taxonomy.

## Out of scope

- The whitelist rule that makes this organization enforceable — see [MDL-001](./MDL-001-boundary-consolidation-whitelist.md).
- The internal contract of the `support/` subdirectory specifically (naming, statelessness, facade consumption) — see [MDL-003](./MDL-003-support-layer-pattern.md).

## Links

- [MDL-001 — Consolidate the Moodle Boundary Behind a Physical Whitelist](./MDL-001-boundary-consolidation-whitelist.md)
- [MDL-003 — Support Layer Pattern](./MDL-003-support-layer-pattern.md)
- [CLAUDE.md](../../CLAUDE.md) — current implementation map
