---
id: MDL-009
title: 'Privacy Provider Delegates to a Specialized Repository'
status: accepted
date: 2026-03-29
domains: [moodle, privacy]
related: [MDL-001, MDL-010]
supersedes: []
superseded_by: null
lang: en
---

# MDL-009: Privacy Provider Delegates to a Specialized Repository

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-309`, decided 2026-03-29, no REF companion — a short, direct ADR). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle's Privacy API (GDPR compliance) imposes static method signatures and platform-owned objects on any implementing provider. Implementing those requirements naively inside the provider itself would spread SQL and procedural logic across a class whose contract is dictated entirely by the platform, not by this framework's own persistence conventions.

## Decision

The Privacy API provider acts **only as an adapter** — it delegates reading, exporting, anonymizing, and deleting to a specialized repository. The delegation preserves the persistence boundary without pretending the Privacy API's integration fits neatly into ordinary domain services. The central provider's scope is limited to data persisted in the framework's own core structures (notably EAV and core framework tables). Extensibility is explicit: an external plugin or an integration with its own storage implements `privacy_provider_interface` (Group A — stable public API); the framework discovers these implementations and delegates to them, in the right order, during export/deletion.

## Consequences

- The Privacy API's platform-mandated shape (static methods, platform objects) never leaks SQL or procedural logic outside the repository boundary.
- External plugins with their own storage get a documented extension point (`privacy_provider_interface`) instead of having to either fork the central provider or skip GDPR compliance.
- Persistence for the central provider's own scope still goes through the repository pattern (out of scope here) — this ADR does not introduce a second persistence mechanism.

## Out of scope

- The repository pattern itself and its persistence conventions — belongs to the framework's own persistence decisions record, not this boundary lib.
- Backup/Restore, which faces a structurally similar "platform API meets repository boundary" problem — see [MDL-010](./MDL-010-backup-restore-import-repository.md).

## Links

- [MDL-010 — Backup/Restore via Import Repository](./MDL-010-backup-restore-import-repository.md)
- [MDL-001 — Consolidate the Moodle Boundary Behind a Physical Whitelist](./MDL-001-boundary-consolidation-whitelist.md)
- [CLAUDE.md](../../CLAUDE.md) — current implementation map (`src/Privacy/PrivacyProvider` + `Privacy/Contract/`)
