---
id: MDL-009
title: 'Privacy Provider Delegates to a Specialized Repository'
status: accepted
date: 2026-03-29
lang: en
domains: [moodle, privacy]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-001, MDL-010]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
decision: 'The Privacy API provider is a thin adapter that delegates read, export, and deletion operations for its own scope to a specialized repository, while external plugins with their own storage participate via `privacy_provider_interface`.'
---

# MDL-009: Privacy Provider Delegates to a Specialized Repository

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-309`, decided 2026-03-29, no REF companion — a short, direct ADR). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle's Privacy API (GDPR compliance) imposes static method signatures and platform-owned objects on any implementing provider. Implementing those requirements naively inside the provider itself would spread SQL and procedural logic across a class whose contract is dictated entirely by the platform, not by this framework's own persistence conventions.

## Considered Options

1. Implement the Privacy API's required operations (metadata, export, anonymize, delete) directly inside the provider class itself, matching Moodle's static-method contract one-to-one.
2. Delegate the provider's own scope to a specialized repository, keeping the provider a thin adapter, and let external plugins with their own storage implement `privacy_provider_interface` so the framework can discover and delegate to them in order — **chosen**.
3. Require external plugins with their own storage to fork or directly extend the central provider instead of exposing an extension interface.
4. Leave external plugins with their own storage without a documented extension point — i.e., accept a GDPR/LGPD compliance gap for that data.

## Decision

The Privacy API provider acts **only as an adapter** — it delegates reading, exporting, anonymizing, and deleting to a specialized repository. The delegation preserves the persistence boundary without pretending the Privacy API's integration fits neatly into ordinary domain services. The central provider's scope is limited to data persisted in the framework's own core structures (notably EAV and core framework tables). Extensibility is explicit: an external plugin or an integration with its own storage implements `privacy_provider_interface` (Group A — stable public API); the framework discovers these implementations and delegates to them, in the right order, during export/deletion.

## Consequences

- The Privacy API's platform-mandated shape (static methods, platform objects) never leaks SQL or procedural logic outside the repository boundary.
- External plugins with their own storage get a documented extension point (`privacy_provider_interface`) instead of having to either fork the central provider or skip GDPR/LGPD compliance.
- Persistence for the central provider's own scope still goes through the repository pattern — that pattern's own conventions belong to the framework's persistence decisions, not to this boundary lib.
- Backup/Restore faces a structurally similar "platform API meets repository boundary" problem, resolved the same way — see [MDL-010](./MDL-010-backup-restore-import-repository.md).
- Current implementation: `src/Privacy/PrivacyProvider.php` (adapter), `src/Privacy/Contract/PrivacyRepositoryInterface.php` (central repository contract) and `src/Privacy/Contract/PrivacyProviderInterface.php` (extension contract) — see this repo's `CLAUDE.md` for the up-to-date module map.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Provider delegates read/export/delete operations for its own scope to the repository instead of implementing them inline | `tests/Privacy/PrivacyProviderCoverageTest.php` (asserts each operation delegates to `PrivacyRepositoryInterface`) | coded |
| External plugins with their own storage participate via `PrivacyProviderInterface`, discovered and delegated to in registration order | Same test file (tagged-provider discovery/delegation and non-provider filtering cases) | coded |
| Central provider's own scope stays limited to framework-owned tables — no ad hoc SQL/procedural logic added directly to the provider | No automated check — structural convention enforced by code review only | planned |
